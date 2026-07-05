<?php

namespace App\Console\Commands;

use App\Models\Categories;
use App\Models\Products;
use App\Services\AutotraderUkParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Crash-safe autotrader.co.uk (UK) scraper — SEARCH GATEWAY ONLY.
 *
 * There are no per-car detail (FPA) calls: the SearchResultsListingsGridQuery
 * GraphQL response already server-renders a complete product per listing
 * (make/model, year, price, mileage, up to ~4 images, dealer, location), so a
 * search-only crawl captures every car at one request per ~20-car page.
 *
 * How the gateway works (all headers/quirks proven by recon):
 *   1. Cloudflare handshake — GET the homepage with a cookie jar to mint the
 *      `__cf_bm` cookie (30-min TTL). Reused for every gateway POST; refreshed
 *      when >25 min old OR on any 403.
 *   2. Gateway POST to /at-gateway?opname=SearchResultsListingsGridQuery with a
 *      JSON-ARRAY body [{operationName, variables, query}]. `channel` is a
 *      top-level var (NOT a filter); the `price_search_type:"total"` filter is
 *      REQUIRED or the response is a 200-with-errors.
 *
 * Sharding past the 100-page cap: each filter set tops out at page 100 (~2,200
 * cars). To cover the full ~450k catalogue the run shards by make (--make) and,
 * for makes that still hit the cap, by price band (--min-price/--max-price).
 * Each shard owns its own cursor/done/progress files (--shard), so shards never
 * collide and the whole thing resumes after a crash.
 *
 * SINGLE-THREADED by design: one request every 2-3s (jittered), no proxies, no
 * concurrency — Cloudflare bans concurrency from one IP. Images are rewritten
 * onto the sm-autotraderuk Bunny pull zone with originals kept in *_source.
 *
 * Prices are GBP (stored verbatim); mileage is MILES stored in the mileage_km
 * column (the unit is noted in specifications).
 */
class ScrapeAutotraderUk extends Command
{
    protected $signature = 'scrape:autotraderuk
        {--start-page= : Override the resume cursor and start from this page}
        {--max-pages=0 : Stop after this many pages this run (0 = all, capped at 100)}
        {--limit=0 : Stop after upserting this many products (0 = all)}
        {--postcode=SW1A 1AA : Search-centre postcode (UK-wide results either way)}
        {--make= : Restrict this shard to one make (e.g. Ford) — for sharding past the 100-page cap}
        {--min-price= : Restrict to cars at/above this price (GBP) — price-band shard}
        {--max-price= : Restrict to cars at/below this price (GBP) — price-band shard}
        {--shard= : Name this worker; gives it its own cursor + done marker + progress}
        {--dry-run : Parse and map but write nothing to the database}
        {--report= : Write an HTML source-vs-database comparison sheet to this path}
        {--delay-ms=2500 : Base pause between gateway requests (jittered 1x..1.4x)}';

    protected $description = 'Scrape autotrader.co.uk into products with Bunny CDN images (search-gateway, single-threaded, resumable, sharded)';

    private const CDN_HOST = 'sm-autotraderuk.b-cdn.net';
    private const ORIGIN_HOST = AutotraderUkParser::IMAGE_HOST; // m.atcdn.co.uk
    private const HOME_URL = AutotraderUkParser::BASE . '/';
    private const GATEWAY_URL = AutotraderUkParser::BASE . '/at-gateway?opname=SearchResultsListingsGridQuery';
    private const OP_NAME = 'SearchResultsListingsGridQuery';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    /** the gateway caps every filter set at page 100 (~2,200 cars) */
    private const PAGE_CAP = 100;

    /** __cf_bm has a 30-min TTL; refresh at 25 min to stay ahead of it */
    private const COOKIE_TTL = 1500;

    private AutotraderUkParser $parser;

    private int $upserted = 0;

    private int $imagesScraped = 0;

    private int $failures = 0;

    private int $soldSkipped = 0;

    private int $startTs = 0;

    private array $reportRows = [];

    private array $categoryIds = [];

    private array $makeIds = [];

    private bool $dryRun = false;

    /** @var array<string,string> cookie name => value, mint from the homepage */
    private array $cookies = [];

    private int $cookieMintedAt = 0;

    private string $query = '';

    public function handle(AutotraderUkParser $parser): int
    {
        $this->parser = $parser;
        $this->upserted = 0;
        $this->imagesScraped = 0;
        $this->failures = 0;
        $this->soldSkipped = 0;
        $this->startTs = time();
        $this->reportRows = [];
        $this->categoryIds = [];
        $this->makeIds = [];
        $this->cookies = [];
        $this->cookieMintedAt = 0;
        $this->query = $this->loadQuery();

        $stateDir = config('cdn.state_dir', storage_path('app/cdn'));
        @mkdir($stateDir, 0777, true);

        // shard scoping: each parallel/sequential shard owns its own cursor,
        // done marker and progress file, keyed by --shard (or make/price if none)
        $shard = $this->option('shard') ?: $this->autoShardName();
        $suffix = $shard ? "-{$shard}" : '';
        $cursorFile = $stateDir . "/autotraderuk{$suffix}.cursor";
        $doneMarker = $stateDir . "/autotraderuk-scrape{$suffix}.done";

        $page = $this->option('start-page') !== null
            ? max(1, (int) $this->option('start-page'))
            : (is_file($cursorFile) ? ((int) file_get_contents($cursorFile)) + 1 : 1);

        $maxPages = (int) $this->option('max-pages');
        $limit = (int) $this->option('limit');
        $this->dryRun = (bool) $this->option('dry-run');
        $pagesDone = 0;

        $this->info(($this->dryRun ? '[dry-run] ' : '') . 'autotraderuk '
            . ($shard ? "shard {$shard} " : '') . 'starting at page ' . $page
            . ($this->option('make') ? ' make=' . $this->option('make') : '')
            . ($this->priceLabel() ? ' price=' . $this->priceLabel() : ''));

        while ($page <= self::PAGE_CAP) {
            $payload = $this->fetchPage($page);
            if ($payload === null) {
                $this->warn("page {$page} unfetchable after retries — stopping so the cursor stays honest");

                return self::FAILURE;
            }

            $search = $this->parser->parseSearchResponse($payload);
            if (!$search['listings']) {
                $this->info("page {$page} has no listings — end of this filter set reached");
                break;
            }

            foreach ($search['listings'] as $listing) {
                if ($limit > 0 && $this->upserted >= $limit) {
                    break 2;
                }
                $this->bankListing($listing);
            }

            if (!$this->dryRun) {
                file_put_contents($cursorFile, (string) $page);
            }
            $pagesDone++;

            // last page = min(total pages in the filter set, the 100 hard cap)
            $lastPage = min(self::PAGE_CAP, $search['last_page'] ?? self::PAGE_CAP);
            $this->writeProgress($stateDir, $suffix, $shard, $page, $lastPage, $search['total']);
            $this->info("page {$page}/{$lastPage} done — {$this->upserted} products banked"
                . ($search['total'] ? ' (filter set ~' . number_format($search['total']) . ' cars)' : ''));

            if ($page >= $lastPage) {
                if ($search['total'] !== null && $search['total'] > self::PAGE_CAP * 20) {
                    $this->warn("filter set has ~{$search['total']} cars but the gateway caps at page "
                        . self::PAGE_CAP . ' — shard by make/price to reach the rest');
                }
                if (!$this->dryRun) {
                    file_put_contents($doneMarker, now()->toDateTimeString() . "\n");
                }
                break;
            }
            if ($maxPages > 0 && $pagesDone >= $maxPages) {
                break;
            }
            $page++;
        }

        if ($this->option('report')) {
            file_put_contents($this->option('report'), $this->renderReport());
            $this->info('comparison sheet written to ' . $this->option('report'));
        }

        $this->info("finished — {$this->upserted} products " . ($this->dryRun ? 'mapped (nothing written)' : 'upserted')
            . ", {$this->soldSkipped} sold/no-price skipped");

        return self::SUCCESS;
    }

    /**
     * Fetch one gateway page, returning the decoded JSON payload (array), or
     * null after exhausting retries. Handles the Cloudflare handshake, cookie
     * refresh, jittered pacing, and 403 -> re-handshake.
     *
     * @return array<mixed>|null
     */
    private function fetchPage(int $page): ?array
    {
        $this->pace();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->ensureSession($attempt > 1);

            try {
                $response = Http::withHeaders($this->gatewayHeaders())
                    ->withOptions(['cookies' => $this->cookieJar()])
                    ->timeout(30)
                    ->post(self::GATEWAY_URL, $this->buildBody($page));

                $status = $response->status();

                if ($status === 403 || $status === 429 || $status === 503) {
                    // challenged / throttled — force a fresh handshake next loop
                    $this->cookies = [];
                    $this->cookieMintedAt = 0;
                    if (app()->runningUnitTests()) {
                        return null;
                    }
                    sleep(min(60, 10 * $attempt));
                    continue;
                }

                if (!$response->successful()) {
                    if (app()->runningUnitTests()) {
                        return null;
                    }
                    sleep($attempt);
                    continue;
                }

                $json = $response->json();
                if (!is_array($json)) {
                    if (app()->runningUnitTests()) {
                        return null;
                    }
                    sleep($attempt);
                    continue;
                }

                // a 200-with-errors (usually a missing required filter) — surface it once
                $root = array_is_list($json) ? ($json[0] ?? []) : $json;
                if (!empty($root['errors'])) {
                    $this->warn('gateway returned errors: ' . json_encode($root['errors']));
                    if (app()->runningUnitTests()) {
                        return null;
                    }
                }

                return $json;
            } catch (\Throwable $e) {
                if (app()->runningUnitTests()) {
                    return null;
                }
                sleep($attempt);
            }
        }

        $this->failures++;

        return null;
    }

    /** jittered pause so the request cadence doesn't read like a bot */
    private function pace(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $delayMs = max(0, (int) $this->option('delay-ms'));
        if ($delayMs > 0) {
            usleep(random_int($delayMs, (int) ($delayMs * 1.4)) * 1000);
        }
    }

    /**
     * Guarantee a live Cloudflare session: (re)GET the homepage to mint __cf_bm
     * when we have no cookie, it's older than the TTL, or a 403 forced a reset.
     */
    private function ensureSession(bool $force = false): void
    {
        $stale = (time() - $this->cookieMintedAt) > self::COOKIE_TTL;
        if (!$force && $this->cookies && !$stale) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-GB,en;q=0.9',
            ])->withOptions(['cookies' => $this->cookieJar()])->timeout(30)->get(self::HOME_URL);

            foreach ($this->extractSetCookies($response) as $name => $value) {
                $this->cookies[$name] = $value;
            }
            $this->cookieMintedAt = time();
        } catch (\Throwable) {
            // leave cookies as-is; the gateway attempt will retry the handshake
        }
    }

    /** Guzzle cookie jar primed with whatever cookies we already hold */
    private function cookieJar(): \GuzzleHttp\Cookie\CookieJar
    {
        return \GuzzleHttp\Cookie\CookieJar::fromArray($this->cookies, 'www.autotrader.co.uk');
    }

    /** @return array<string,string> Set-Cookie name => value from a response */
    private function extractSetCookies($response): array
    {
        $out = [];
        foreach ((array) $response->header('Set-Cookie') as $line) {
            if (preg_match('/^([^=]+)=([^;]*)/', trim($line), $m)) {
                $out[trim($m[1])] = $m[2];
            }
        }

        // Laravel folds multi-header values; also read the raw header bag
        foreach ($response->headers()['Set-Cookie'] ?? [] as $line) {
            if (preg_match('/^([^=]+)=([^;]*)/', trim($line), $m)) {
                $out[trim($m[1])] = $m[2];
            }
        }

        return $out;
    }

    /** @return array<string,string> */
    private function gatewayHeaders(): array
    {
        return [
            'User-Agent' => self::USER_AGENT,
            'Content-Type' => 'application/json',
            'Accept' => '*/*',
            'Accept-Language' => 'en-GB,en;q=0.9',
            'Origin' => AutotraderUkParser::BASE,
            'Referer' => AutotraderUkParser::BASE . '/car-search',
            'x-sauron-app-name' => 'sauron-search-results-app',
        ];
    }

    /**
     * The JSON-ARRAY gateway body. `channel` is a top-level var; the postcode +
     * `price_search_type:total` filters are required, then optional make / price
     * shard filters.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildBody(int $page): array
    {
        $filters = [
            ['filter' => 'postcode', 'selected' => [(string) $this->option('postcode')]],
            ['filter' => 'price_search_type', 'selected' => ['total']],
        ];

        if ($make = $this->option('make')) {
            $filters[] = ['filter' => 'make', 'selected' => [$make]];
        }
        if (($min = $this->option('min-price')) !== null && $min !== '') {
            $filters[] = ['filter' => 'min_price', 'selected' => [(string) (int) $min]];
        }
        if (($max = $this->option('max-price')) !== null && $max !== '') {
            $filters[] = ['filter' => 'max_price', 'selected' => [(string) (int) $max]];
        }

        return [[
            'operationName' => self::OP_NAME,
            'variables' => [
                'filters' => $filters,
                'channel' => 'cars',
                'page' => $page,
                'sortBy' => 'relevance',
                'listingType' => null,
                'searchId' => '00000000-0000-0000-0000-000000000000',
                'featureFlags' => [],
            ],
            'query' => $this->query,
        ]];
    }

    /** @param array<string,mixed> $listing */
    private function bankListing(array $listing): void
    {
        $url = $listing['product_link'];

        if (empty($listing['images'])) {
            $this->logFailure($url, 'no images on listing');
        }

        $attributes = $this->mapToProduct($listing);

        if ($this->option('report') && count($this->reportRows) < 100) {
            $this->reportRows[] = ['source' => $listing, 'mapped' => $attributes];
        }

        if (!$this->dryRun) {
            $product = Products::updateOrCreate(['product_link' => $url], $attributes);
            if (!$product->stock_code) {
                $product->update(['stock_code' => 'SM' . $product->id]);
            }
        }

        $this->imagesScraped += count($listing['images'] ?? []);
        $this->upserted++;
    }

    /** @param array<string,mixed> $data */
    private function mapToProduct(array $data): array
    {
        $sourceImages = $data['images'] ?? [];
        $images = array_map($this->toCdn(...), $sourceImages);

        return [
            'title' => mb_substr($data['title'], 0, 500),
            'model' => !empty($data['model']) ? mb_substr($data['model'], 0, 100) : null,
            'year' => $data['year'] ?? null,
            'engine_cc' => $data['engine_cc'] ?? null,
            'mileage_km' => $data['mileage_km'] ?? null, // miles (unit noted in specifications)
            'fuel' => $data['fuel'] ?? null,
            'transmission' => $data['transmission'] ?? null,
            'condition' => $data['condition'] ?? null,
            'color' => $data['color'] ?? null,
            'steering' => $data['steering'] ?? 'Right',
            'seats' => $data['seats'] ?? null,
            'doors' => $data['doors'] ?? null,
            'drive_type' => $data['drive_type'] ?? null,
            'power_hp' => $data['power_hp'] ?? null,
            'category_id' => $this->categoryIdFor($data['body_style'] ?? null, $data['title'] ?? null),
            'make_id' => !empty($data['make']) ? $this->makeId($data['make']) : null,
            'price' => $data['price'] ?? null, // GBP, stored verbatim
            'country' => $data['country'] ?? 'United Kingdom',
            'website' => 'autotraderuk',
            'body_style' => $data['body_style'] ?? null,
            'product_link' => $data['product_link'],
            'front_image' => $images[0] ?? null,
            'front_image_source' => $sourceImages[0] ?? null,
            'other_images' => array_slice($images, 1),
            'other_images_source' => json_encode(array_slice($sourceImages, 1)),
            'product_details' => $data['product_details'] ?? '',
            'specifications' => $data['specifications'] ?? null,
        ];
    }

    private function toCdn(string $url): string
    {
        return str_replace('https://' . self::ORIGIN_HOST, 'https://' . self::CDN_HOST, $url);
    }

    /** route body_style + title into a real category (Cars fallback), cached */
    private function categoryIdFor(?string $bodyStyle, ?string $title): ?int
    {
        $name = app(\App\Services\CategoryRouter::class)->resolve($bodyStyle, $title);

        return $this->categoryIds[$name] ??= (
            Categories::where('cat_title', $name)->where('type', 'category')->value('id')
            ?? $this->categoryIds['Cars']
            ?? Categories::where('cat_title', 'Cars')->where('type', 'category')->value('id')
        );
    }

    private function makeId(string $name): ?int
    {
        $name = app(\App\Services\MakeNormalizer::class)->canonical($name) ?? $name;

        if ($this->dryRun) {
            return $this->makeIds[$name] ??= Categories::where('cat_title', $name)->where('type', 'make')->value('id');
        }

        return $this->makeIds[$name] ??= Categories::firstOrCreate(
            ['cat_title' => $name, 'type' => 'make'],
        )->id;
    }

    private function loadQuery(): string
    {
        $path = resource_path('graphql/autotraderuk-search.graphql');
        $query = is_file($path) ? file_get_contents($path) : '';
        if (trim($query) === '') {
            throw new \RuntimeException("GraphQL query missing at {$path}");
        }

        return $query;
    }

    /** default shard name from make/price when --shard isn't given (keeps state files apart) */
    private function autoShardName(): ?string
    {
        $bits = array_filter([
            $this->option('make') ? preg_replace('/[^a-z0-9]+/i', '', strtolower($this->option('make'))) : null,
            $this->priceLabel() ? 'p' . preg_replace('/[^0-9]+/', '_', $this->priceLabel()) : null,
        ]);

        return $bits ? implode('-', $bits) : null;
    }

    private function priceLabel(): ?string
    {
        $min = $this->option('min-price');
        $max = $this->option('max-price');
        if (($min === null || $min === '') && ($max === null || $max === '')) {
            return null;
        }

        return (($min !== null && $min !== '') ? (int) $min : '0') . '-' . (($max !== null && $max !== '') ? (int) $max : 'max');
    }

    private function writeProgress(string $stateDir, string $suffix, ?string $shard, int $page, ?int $lastPage, ?int $total): void
    {
        $elapsed = max(1, time() - $this->startTs);
        $ratePerMin = round($this->upserted / $elapsed * 60, 1);

        $snapshot = [
            'source' => 'autotraderuk',
            'shard' => $shard,
            'make' => $this->option('make') ?: null,
            'price_band' => $this->priceLabel(),
            'page' => $page,
            'last_page' => $lastPage,
            'percent' => $lastPage ? round($page / $lastPage * 100, 1) : null,
            'products_scraped' => $this->upserted,
            'filter_set_total' => $total,
            'images_scraped' => $this->imagesScraped,
            'failures' => $this->failures,
            'rate_per_min' => $ratePerMin,
            'started_at' => date('c', $this->startTs),
            'updated_at' => date('c'),
        ];

        file_put_contents(
            $stateDir . "/autotraderuk-progress{$suffix}.json",
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        file_put_contents(
            $stateDir . "/autotraderuk-heartbeat{$suffix}.txt",
            date('c') . " page {$page}/" . ($lastPage ?? '?')
            . " products={$this->upserted} images={$this->imagesScraped}\n"
        );
    }

    private function logFailure(string $url, string $reason): void
    {
        $this->failures++;
        $this->warn("skip {$url}: {$reason}");
        file_put_contents(
            config('cdn.state_dir', storage_path('app/cdn')) . '/autotraderuk-failures.log',
            now()->toDateTimeString() . "\t{$url}\t{$reason}\n",
            FILE_APPEND
        );
    }

    private function renderReport(): string
    {
        $rows = '';
        foreach ($this->reportRows as $i => $row) {
            $s = $row['source'];
            $m = $row['mapped'];
            $img = $m['front_image'] ? '<img src="' . e($m['front_image']) . '" loading="lazy">' : '<div class="noimg">no image</div>';
            $gallery = count($m['other_images']) + ($m['front_image'] ? 1 : 0);
            $gbp = $m['price'] !== null ? '£' . number_format((float) $m['price']) : '<span class="enq">Enquire</span>';
            $specBits = array_filter([
                ($m['engine_cc'] ?? null) ? $m['engine_cc'] . 'cc' : null,
                $m['fuel'] ?? null,
                $m['transmission'] ?? null,
                ($s['specifications']['vehicleLocation'] ?? null),
            ]);
            $rows .= '<tr>'
                . '<td class="n">' . ($i + 1) . '</td>'
                . '<td>' . $img . '</td>'
                . '<td><a href="' . e($s['product_link']) . '" target="_blank" rel="noopener">' . e($m['title']) . '</a>'
                . '<div class="sub">' . e($s['specifications']['subTitle'] ?? '') . '</div>'
                . '<div class="src"><a href="' . e($s['product_link']) . '" target="_blank" rel="noopener">↗ view on AutoTrader UK</a></div></td>'
                . '<td class="price"><b>' . $gbp . '</b></td>'
                . '<td>' . e($m['condition'] ?? '—') . '</td>'
                . '<td>' . e((string) ($m['year'] ?? '—')) . '</td>'
                . '<td>' . ($m['mileage_km'] !== null ? number_format($m['mileage_km']) . ' mi' : '—') . '</td>'
                . '<td>' . e($m['fuel'] ?? '—') . ' / ' . e($m['transmission'] ?? '—') . '</td>'
                . '<td class="specs">' . ($specBits ? e(implode(' · ', $specBits)) : '<span class="sub">search-only</span>') . '</td>'
                . '<td class="ph"><b>' . $gallery . '</b>' . (($s['specifications']['numberOfImages'] ?? null) ? ' / ' . $s['specifications']['numberOfImages'] . ' avail' : '') . '</td>'
                . '</tr>';
        }

        return '<!doctype html><meta charset="utf-8"><title>AutoTrader UK scrape preview</title><style>'
            . 'body{font-family:Segoe UI,system-ui,sans-serif;background:#eceff4;margin:0;color:#0b1e3b}'
            . '.wrap{max-width:1600px;margin:0 auto;padding:26px}'
            . 'h1{font-size:23px;margin:0 0 4px}.lede{color:#5b6b83;font-size:14px;margin:0 0 18px;max-width:90ch}'
            . 'table{border-collapse:collapse;width:100%;background:#fff;font-size:13px;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(11,30,59,.06)}'
            . 'th{background:#0b1e3b;color:#fff;padding:11px 10px;text-align:left;font-size:11px;letter-spacing:.03em;text-transform:uppercase;position:sticky;top:0}'
            . 'td{border-bottom:1px solid #eef1f6;padding:10px;vertical-align:top}tr:hover td{background:#f8fafc}'
            . 'img{width:150px;height:112px;object-fit:cover;border-radius:8px;background:#eef1f6;display:block}'
            . '.noimg{width:150px;height:112px;border-radius:8px;background:#eef1f6;display:grid;place-items:center;color:#b3bece;font-size:11px}'
            . 'a{color:#2456b8;text-decoration:none;font-weight:700}a:hover{text-decoration:underline}'
            . '.sub{color:#8494ab;font-size:11px;margin-top:2px}.src a{color:#e01f26;font-size:11px}'
            . '.price b{font-size:15px;color:#0b1e3b}.enq{color:#b5591a}.specs{max-width:220px;color:#33445e}'
            . '.ph b{color:#1f8f57}.n{color:#b3bece;font-variant-numeric:tabular-nums}'
            . '</style><div class="wrap"><h1>AutoTrader UK → SupremeMotors · ' . count($this->reportRows) . ' products</h1>'
            . '<p class="lede">Search-gateway only (no per-car detail calls). Prices are <b>GBP</b>, stored verbatim. Mileage is in <b>miles</b> (stored in the mileage_km column; unit noted in specifications). ~4 images per car is expected from the search response. Fuel/transmission are best-effort from the subTitle. Images point at the sm-autotraderuk Bunny CDN; originals kept in *_source.</p>'
            . '<table><tr><th>#</th><th>Image (CDN)</th><th>Title / source link</th><th>Price GBP</th><th>Condition</th><th>Year</th><th>Mileage</th><th>Fuel / Gearbox</th><th>Detail</th><th>Photos</th></tr>'
            . $rows . '</table></div>';
    }
}
