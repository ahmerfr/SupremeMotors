<?php

namespace App\Console\Commands;

use App\Models\Categories;
use App\Models\Products;
use App\Services\AutotraderParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Crash-safe autotrader.co.za scraper.
 *
 * Fast path (default): the search pages server-render 25 fully-populated
 * listings each — title, make/model, year, price, mileage, fuel, transmission,
 * condition, front image + gallery shots. Crawling only the ~3,700 search
 * pages therefore captures all ~93k cars at 1/25th the request count of
 * fetching every detail page. --deep adds a per-listing detail fetch for the
 * full 20-image gallery and exhaustive spec sheet.
 *
 * Resumable / re-runnable / outage-proof: the page cursor checkpoints after
 * each completed page, listings upsert by product_link (banked rows are never
 * refetched without --refresh), fetches ride quick backoff retries then an
 * outage loop that waits out long net drops. Optional proxy rotation
 * (--proxy-file) spreads requests across IPs and auto-advances off any proxy
 * the origin starts 429/503-blocking, so a single-IP ban never stalls the run.
 * Images are rewritten onto the sm-autotrader Bunny pull zone with originals
 * kept in the *_source columns, matching the other sources' CDN convention.
 */
class ScrapeAutotrader extends Command
{
    protected $signature = 'scrape:autotrader
        {--start-page= : Override the resume cursor and start from this search page}
        {--max-pages=0 : Stop after this many search pages (0 = all)}
        {--limit=0 : Stop after upserting this many products (0 = all)}
        {--shard= : Name this worker; gives it its own cursor + done marker + progress (for parallel shards)}
        {--min-page=1 : First search page this shard owns}
        {--max-page=0 : Last search page this shard owns (0 = to the end)}
        {--deep : Fetch each detail page for the full gallery, seats/doors, engine + spec sheet (accurate mode)}
        {--fill-incomplete : Skip crawling; re-fetch detail for any autotraderza product missing it, until none remain}
        {--dry-run : Parse and map but write nothing to the database}
        {--report= : Write an HTML source-vs-database comparison sheet to this path}
        {--refresh : Re-scrape listings that already exist in the database}
        {--delay-ms=2500 : Base pause between requests (jittered); auto-drops when proxies rotate}
        {--search-direct=1 : Fetch search pages via the home IP (fast); proxies carry the detail flood}
        {--pool=1 : Concurrent detail fetches (only raise past 1 with proxies — a single IP bans)}
        {--proxy-file= : Newline-delimited proxy list (host:port or user:pass@host:port); rotates + fails over on ban}
        {--usd-rate=0.055 : Convert ZAR prices to USD at this rate (0 = store raw ZAR); default ~R18.2/$}';

    protected $description = 'Scrape autotrader.co.za into products with Bunny CDN images (search-first, resumable, proxy-rotating)';

    private const CDN_HOST = 'sm-autotrader.b-cdn.net';
    private const ORIGIN_HOST = AutotraderParser::IMAGE_HOST;
    private const SEARCH_URL = AutotraderParser::BASE . '/cars-for-sale';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private AutotraderParser $parser;

    private int $upserted = 0;

    private int $imagesScraped = 0;

    private int $failures = 0;

    private int $startTs = 0;

    private array $reportRows = [];

    private ?int $carsCategoryId = null;

    private array $makeIds = [];

    private bool $dryRun = false;

    /** @var string[] */
    private array $proxies = [];

    private int $proxyIdx = 0;

    private int $proxyFileMtime = 0;

    public function handle(AutotraderParser $parser): int
    {
        $this->parser = $parser;
        $this->upserted = 0;
        $this->imagesScraped = 0;
        $this->failures = 0;
        $this->startTs = time();
        $this->reportRows = [];
        $this->carsCategoryId = null;
        $this->makeIds = [];
        $this->loadProxies();

        $stateDir = config('cdn.state_dir', storage_path('app/cdn'));
        @mkdir($stateDir, 0777, true);

        if ($this->option('fill-incomplete')) {
            return $this->fillIncomplete($stateDir);
        }

        // shard scoping: each parallel worker owns a page range with its own
        // cursor, done marker, and progress file so shards never collide
        $shard = $this->option('shard');
        $suffix = $shard ? "-{$shard}" : '';
        $cursorFile = $stateDir . "/autotrader{$suffix}.cursor";
        $doneMarker = $stateDir . "/autotrader-scrape{$suffix}.done";

        $minPage = max(1, (int) $this->option('min-page'));
        $maxPage = (int) $this->option('max-page') ?: null;

        $page = $this->option('start-page') !== null
            ? max(1, (int) $this->option('start-page'))
            : (is_file($cursorFile) ? max($minPage, ((int) file_get_contents($cursorFile)) + 1) : $minPage);

        $maxPages = (int) $this->option('max-pages');
        $limit = (int) $this->option('limit');
        $dryRun = $this->dryRun = (bool) $this->option('dry-run');
        $deep = (bool) $this->option('deep');
        $pagesDone = 0;

        $this->info(($dryRun ? '[dry-run] ' : '') . ($deep ? '[deep] ' : '[search] ')
            . ($shard ? "shard {$shard} " : '') . 'starting at page ' . $page
            . ($maxPage ? " (range {$minPage}-{$maxPage})" : '')
            . ($this->proxies ? ' via ' . count($this->proxies) . ' proxies' : ''));

        while (true) {
            $this->reloadProxiesIfChanged(); // pick up a background proxy refresh
            $html = $this->fetchSearch(self::SEARCH_URL . '?pagenumber=' . $page);
            if ($html === null) {
                $this->warn("search page {$page} unfetchable after retries — stopping so the cursor stays honest");

                return self::FAILURE;
            }

            $search = $this->parser->parseSearchListings($html);
            if (!$search['listings']) {
                $this->info("page {$page} has no listings — end of inventory reached");
                break;
            }

            // pick the tiles we actually need this page (respect resume + limit)
            $pending = [];
            foreach ($search['listings'] as $tile) {
                if ($limit > 0 && count($pending) + $this->upserted >= $limit) {
                    break;
                }
                if ($this->option('refresh') || $dryRun
                    || !Products::where('product_link', $tile['product_link'])->exists()) {
                    $pending[] = $tile;
                }
            }

            // deep mode: fetch all this page's detail pages concurrently (proxy-spread),
            // then merge each over its search tile. search mode banks tiles directly.
            $details = $deep ? $this->fetchDetailBatch(array_column($pending, 'product_link')) : [];

            foreach ($pending as $tile) {
                if ($limit > 0 && $this->upserted >= $limit) {
                    break 2;
                }
                $this->bankTile($tile, $deep ? ($details[$tile['product_link']] ?? null) : null, $dryRun, $deep);
            }

            // limit reached but this page yielded no pending tiles — stop the
            // loop instead of crawling on through empty search pages
            if ($limit > 0 && $this->upserted >= $limit) {
                break;
            }

            if (!$dryRun) {
                file_put_contents($cursorFile, (string) $page);
            }
            $pagesDone++;
            $shardEnd = $maxPage ? min($maxPage, $search['last_page'] ?? $maxPage) : $search['last_page'];
            $this->writeProgress($stateDir, $suffix, $page, $shardEnd, $search['total'], false);
            $this->info("page {$page}/" . ($shardEnd ?? '?')
                . ' done — ' . $this->upserted . ' products banked'
                . ($search['total'] ? ' (of ~' . number_format($search['total']) . ')' : ''));

            $atEnd = ($search['last_page'] !== null && $page >= $search['last_page'])
                || ($maxPage !== null && $page >= $maxPage);
            if ($atEnd) {
                $this->writeProgress($stateDir, $suffix, $page, $shardEnd, $search['total'], true);
                if (!$dryRun) {
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

        $this->info("finished — {$this->upserted} products " . ($dryRun ? 'mapped (nothing written)' : 'upserted'));

        return self::SUCCESS;
    }

    /**
     * Write a machine + human readable progress snapshot every page. The
     * keepalive task and any status page read this; it's how the run reports
     * itself while unattended. Also drops a plain-text heartbeat line.
     */
    private function writeProgress(string $stateDir, string $suffix, int $page, ?int $lastPage, ?int $total, bool $done): void
    {
        $elapsed = max(1, time() - $this->startTs);
        $ratePerMin = round($this->upserted / $elapsed * 60, 1);
        $pagesLeft = $lastPage ? max(0, $lastPage - $page) : null;
        // ~25 products/page; ETA from the observed product rate
        $etaMin = ($pagesLeft !== null && $ratePerMin > 0)
            ? (int) round($pagesLeft * 25 / $ratePerMin)
            : null;

        $snapshot = [
            'mode' => $this->option('deep') ? 'deep' : 'search',
            'shard' => $this->option('shard') ?: null,
            'done' => $done,
            'page' => $page,
            'last_page' => $lastPage,
            'percent' => $lastPage ? round($page / $lastPage * 100, 1) : null,
            'products_scraped' => $this->upserted,
            'products_total_estimate' => $total,
            'images_scraped' => $this->imagesScraped,
            'failures' => $this->failures,
            'proxies_live' => count($this->proxies),
            'rate_per_min' => $ratePerMin,
            'eta_minutes' => $etaMin,
            'started_at' => date('c', $this->startTs),
            'updated_at' => date('c'),
        ];

        file_put_contents(
            $stateDir . "/autotrader-progress{$suffix}.json",
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        file_put_contents(
            $stateDir . "/autotrader-heartbeat{$suffix}.txt",
            date('c') . " page {$page}/" . ($lastPage ?? '?')
            . " products={$this->upserted} images={$this->imagesScraped}"
            . ($etaMin !== null ? " eta~{$etaMin}min" : '') . "\n"
        );
    }

    /**
     * Guarantee full data: re-fetch the detail page for every autotraderza
     * product whose specifications is still NULL (its detail never came through
     * during the crawl), updating it in place with the complete gallery + specs,
     * and loop until none remain. Genuinely-withdrawn listings (404/410) are
     * deleted since they can never be completed. Writes autotrader-fill.done
     * only when zero incomplete products remain — the keepalive gates on it.
     */
    private function fillIncomplete(string $stateDir): int
    {
        $doneMarker = $stateDir . '/autotrader-fill.done';
        @unlink($doneMarker);

        for ($round = 1; ; $round++) {
            $this->reloadProxiesIfChanged();

            $remaining = Products::where('website', 'autotraderza')->whereNull('specifications')->count();
            file_put_contents(
                $stateDir . '/autotrader-fill-progress.json',
                json_encode(['incomplete_remaining' => $remaining, 'updated_at' => date('c')], JSON_PRETTY_PRINT)
            );

            if ($remaining === 0) {
                file_put_contents($doneMarker, now()->toDateTimeString() . "\n");
                $this->info('fill-incomplete: every autotraderza product has full detail');

                return self::SUCCESS;
            }

            $links = Products::where('website', 'autotraderza')->whereNull('specifications')
                ->limit(300)->pluck('product_link')->all();
            $this->info("fill-incomplete round {$round}: {$remaining} missing detail — fetching " . count($links));

            $details = $this->fetchDetailBatch($links);
            $filled = 0;
            $failed = [];
            foreach ($links as $url) {
                $html = $details[$url] ?? null;
                $detail = $html !== null ? $this->parser->parseDetailPage($html, $url) : null;
                if ($detail !== null && !empty($detail['images'])) {
                    Products::where('product_link', $url)->update($this->mapToProduct($detail));
                    $filled++;
                } else {
                    $failed[] = $url;
                }
            }
            $this->info("fill-incomplete round {$round}: filled {$filled}, still failing " . count($failed));

            if ($filled === 0) {
                // nothing came through this round — delete any that are genuinely
                // withdrawn (404/410), then hand back so the keepalive retries the
                // rest with a fresher proxy pool
                $this->pruneWithdrawn(array_slice($failed, 0, 25));

                return self::SUCCESS;
            }
            // loop continues: remaining shrank, so it terminates when it hits 0
        }
    }

    /** delete products whose listing is gone from AutoTrader (404/410) */
    private function pruneWithdrawn(array $urls): void
    {
        foreach ($urls as $url) {
            try {
                $code = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(15)->get($url)->status();
                if (in_array($code, [404, 410], true)) {
                    Products::where('product_link', $url)->delete();
                    $this->warn("fill-incomplete: deleted withdrawn listing {$url}");
                }
            } catch (\Throwable) {
            }
            if (! app()->runningUnitTests()) {
                usleep(400000); // gentle on the home IP
            }
        }
    }

    /**
     * @param array<string,mixed> $tile   basic search-tile data
     * @param string|null         $detailHtml pre-fetched detail HTML (deep mode)
     */
    private function bankTile(array $tile, ?string $detailHtml, bool $dryRun, bool $deep): void
    {
        $url = $tile['product_link'];
        $data = $tile;

        if ($deep) {
            if ($detailHtml !== null) {
                $detail = $this->parser->parseDetailPage($detailHtml, $url);
                if ($detail !== null) {
                    // detail page wins on gallery + seats/doors/engine; keep tile fields it lacks
                    $data = array_merge($tile, array_filter($detail, fn ($v) => $v !== null && $v !== []));
                } else {
                    $this->logFailure($url, 'detail unparseable — kept search-tile data');
                }
            } else {
                $this->logFailure($url, 'detail unfetchable — kept search-tile data');
            }
        }

        if (empty($data['images'])) {
            $this->logFailure($url, 'no images on listing');
        }

        $attributes = $this->mapToProduct($data);

        if ($this->option('report') && count($this->reportRows) < 100) {
            $this->reportRows[] = ['source' => $data, 'mapped' => $attributes];
        }

        if (!$dryRun) {
            $product = Products::updateOrCreate(['product_link' => $url], $attributes);
            if (!$product->stock_code) {
                $product->update(['stock_code' => 'SM' . $product->id]);
            }
        }

        $this->imagesScraped += count($data['images'] ?? []);
        $this->upserted++;
    }

    /**
     * Fetch many detail pages, returning [url => html|null]. With proxies and
     * --pool>1 it runs a rolling concurrent window (each request on the next
     * proxy), retrying ban/connection failures on fresh proxies across rounds.
     * Without proxies it falls back to the polite sequential fetcher, since a
     * single IP is banned by concurrency. Tests use the fakeable sequential path.
     *
     * @param  string[]  $urls
     * @return array<string,string|null>
     */
    private function fetchDetailBatch(array $urls): array
    {
        $poolSize = max(1, (int) $this->option('pool'));

        if ($poolSize === 1 || !$this->proxies || app()->runningUnitTests()) {
            $out = [];
            foreach ($urls as $url) {
                $out[$url] = $this->fetch($url);
            }

            return $out;
        }

        $results = [];
        $pending = array_values($urls);

        for ($round = 1; $round <= 6 && $pending !== []; $round++) {
            // short connect timeout: free proxies die constantly, and a dead one
            // must fail in ~4s (not 12) so the live proxies carry the throughput
            $client = new \GuzzleHttp\Client([
                'connect_timeout' => 4,
                'timeout' => 20,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-ZA,en;q=0.9',
                    'Referer' => self::SEARCH_URL,
                ],
            ]);

            $retry = [];
            $usedProxy = []; // url => proxy, so a failure can evict the dead proxy
            $requests = function () use ($pending, $client, &$usedProxy) {
                foreach ($pending as $url) {
                    $opts = [];
                    if ($proxy = $this->currentProxy()) {
                        $opts['proxy'] = $proxy;
                        $usedProxy[$url] = $proxy;
                    }
                    $this->proxyIdx++; // spread each request across the pool
                    yield $url => $client->getAsync($url, $opts);
                }
            };

            \GuzzleHttp\Promise\Each::ofLimit(
                $requests(),
                $poolSize,
                function ($resp, $url) use (&$results, &$retry) {
                    $code = $resp->getStatusCode();
                    if ($code >= 200 && $code < 300) {
                        $results[$url] = (string) $resp->getBody();
                    } elseif (in_array($code, [404, 410], true)) {
                        $results[$url] = null; // withdrawn
                    } else {
                        $retry[] = $url; // 403/429/503 — try again on another proxy
                    }
                },
                function ($_e, $url) use (&$retry, &$usedProxy) {
                    $retry[] = $url;
                    if (isset($usedProxy[$url])) {
                        $this->evictProxy($usedProxy[$url]); // connection failed — drop this dead proxy
                    }
                }
            )->wait();

            $pending = $retry;
            if ($pending !== [] && $round < 6) {
                sleep(2 * $round); // let banned proxies cool before the next round
            }
        }

        foreach ($pending as $url) {
            $results[$url] = null; // exhausted retries
            $this->logFailure($url, 'detail unfetchable after pooled retries');
        }

        return $results;
    }

    /** @param array<string,mixed> $data */
    private function mapToProduct(array $data): array
    {
        $images = array_map($this->toCdn(...), $data['images'] ?? []);
        $rate = (float) $this->option('usd-rate');
        $rawPrice = $data['price'] ?? null;
        $price = $rawPrice !== null && $rate > 0 ? round($rawPrice * $rate, 2) : $rawPrice;
        $sourceImages = $data['images'] ?? [];

        return [
            'title' => mb_substr($data['title'], 0, 500),
            'model' => !empty($data['model']) ? mb_substr($data['model'], 0, 100) : null,
            'year' => $data['year'] ?? null,
            'engine_cc' => $data['engine_cc'] ?? null,
            'mileage_km' => $data['mileage_km'] ?? null,
            'fuel' => $data['fuel'] ?? null,
            'transmission' => $data['transmission'] ?? null,
            'condition' => $data['condition'] ?? null,
            'color' => $data['color'] ?? null,
            'steering' => $data['steering'] ?? 'Right',
            'seats' => $data['seats'] ?? null,
            'doors' => $data['doors'] ?? null,
            'drive_type' => $data['drive_type'] ?? null,
            'power_hp' => $data['power_hp'] ?? null,
            'category_id' => $this->carsCategoryId(),
            'make_id' => !empty($data['make']) ? $this->makeId($data['make']) : null,
            'price' => $price,
            'country' => $data['country'] ?? 'South Africa',
            'website' => 'autotraderza',
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

    private function carsCategoryId(): ?int
    {
        return $this->carsCategoryId ??= Categories::where('cat_title', 'Cars')->where('type', 'category')->value('id');
    }

    private function makeId(string $name): ?int
    {
        // dry-run must not persist: look up existing makes only, never create
        if ($this->dryRun) {
            return $this->makeIds[$name] ??= Categories::where('cat_title', $name)->where('type', 'make')->value('id');
        }

        return $this->makeIds[$name] ??= Categories::firstOrCreate(
            ['cat_title' => $name, 'type' => 'make'],
        )->id;
    }

    private function loadProxies(): void
    {
        $this->proxies = [];
        $this->proxyIdx = 0;
        $this->proxyFileMtime = 0;
        $this->reloadProxiesIfChanged();
    }

    /**
     * Re-read the proxy file when it changes on disk, so a background
     * `scrape:refresh-proxies` run can top up the live pool mid-scrape without
     * a restart. Free proxies die constantly — this keeps the window supplied.
     */
    private function reloadProxiesIfChanged(): void
    {
        $file = $this->option('proxy-file');
        if (!$file || !is_file($file)) {
            return;
        }
        clearstatcache(true, $file);
        $mtime = (int) filemtime($file);
        if ($mtime === $this->proxyFileMtime) {
            return;
        }

        $fresh = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $fresh[] = $line;
            }
        }
        if ($fresh) {
            $this->proxies = $fresh;
            $this->proxyIdx = 0;
            $this->proxyFileMtime = $mtime;
        }
    }

    /**
     * Fetch with three layers of resilience: proxy rotation (advance off any IP
     * the origin starts blocking), quick retries with backoff for transient
     * blips, then an outage loop that waits a minute between rounds so a long
     * net drop pauses the scrape instead of failing it.
     */
    private function fetch(string $url): ?string
    {
        $delayMs = max(0, (int) $this->option('delay-ms'));
        if ($delayMs > 0) {
            usleep(random_int($delayMs, (int) ($delayMs * 1.6)) * 1000); // jitter reads less like a bot
        }

        for ($round = 1; ; $round++) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                try {
                    $request = Http::withHeaders([
                        'User-Agent' => self::USER_AGENT,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'en-ZA,en;q=0.9',
                        'Referer' => self::SEARCH_URL,
                    ])->timeout(30);

                    if ($proxy = $this->currentProxy()) {
                        $request = $request->withOptions(['proxy' => $proxy]);
                    }

                    $response = $request->get($url);

                    if ($response->successful()) {
                        return $response->body();
                    }
                    if (in_array($response->status(), [404, 410], true)) {
                        return null; // listing withdrawn — a fact, not an outage
                    }
                    if (in_array($response->status(), [403, 429, 503], true)) {
                        // this IP is blocked/challenged: rotate to a fresh proxy if we have one
                        if ($this->rotateProxy()) {
                            continue;
                        }
                        sleep(min(90, 15 * $attempt));
                        continue;
                    }
                } catch (\Throwable) {
                    $this->rotateProxy(); // dead proxy or connection error — try the next
                }
                sleep($attempt);
            }

            if ($round >= 3 && !$this->originAlive()) {
                $this->warn("net/origin outage — waiting 60s (round {$round}) on {$url}");
            }
            if (app()->runningUnitTests()) {
                return null; // never spin forever inside the test suite
            }
            sleep(60);
        }
    }

    /**
     * Fetch a search-results page. The ~3,700 search pages are the serial spine
     * of the crawl, so we run them over the fast home IP (2s) instead of a slow
     * free proxy (5-15s) — the search rate is low (one per detail batch) so the
     * home IP's rate limit is never hit. On a home-IP 403/503 it falls back to
     * the proxy pool for that page. Tests + --search-direct=0 use the pool path.
     */
    private function fetchSearch(string $url): ?string
    {
        if (!(bool) $this->option('search-direct') || app()->runningUnitTests()) {
            return $this->fetch($url);
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-ZA,en;q=0.9',
                    'Referer' => self::SEARCH_URL,
                ])->timeout(20)->get($url); // no proxy: home IP

                if ($response->successful()) {
                    return $response->body();
                }
                if (in_array($response->status(), [404, 410], true)) {
                    return null;
                }
                if (in_array($response->status(), [403, 429, 503], true)) {
                    break; // home IP throttled — hand this page to the proxy pool
                }
            } catch (\Throwable) {
                break;
            }
            sleep($attempt);
        }

        return $this->proxies ? $this->fetch($url) : null;
    }

    private function currentProxy(): ?string
    {
        if (!$this->proxies) {
            return null;
        }
        $p = $this->proxies[$this->proxyIdx % count($this->proxies)];

        return str_contains($p, '://') ? $p : 'http://' . $p;
    }

    private function rotateProxy(): bool
    {
        if (count($this->proxies) < 2) {
            return false;
        }
        $this->proxyIdx++;

        return true;
    }

    /**
     * Drop a proxy that just failed to connect. Free proxies die constantly;
     * evicting them keeps the pool converging on the live ones so retries stop
     * landing on corpses. The keepalive's periodic refresh refills the pool.
     */
    private function evictProxy(string $proxy): void
    {
        $bare = str_starts_with($proxy, 'http://') ? substr($proxy, 7) : $proxy;
        $this->proxies = array_values(array_filter(
            $this->proxies,
            fn ($p) => $p !== $bare && $p !== $proxy
        ));
    }

    private function originAlive(): bool
    {
        try {
            return Http::timeout(10)->head(AutotraderParser::BASE)->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    private function logFailure(string $url, string $reason): void
    {
        $this->failures++;
        $this->warn("skip {$url}: {$reason}");
        file_put_contents(
            config('cdn.state_dir', storage_path('app/cdn')) . '/autotrader-failures.log',
            now()->toDateTimeString() . "\t{$url}\t{$reason}\n",
            FILE_APPEND
        );
    }

    private function renderReport(): string
    {
        $rate = (float) $this->option('usd-rate');
        $rows = '';
        foreach ($this->reportRows as $i => $row) {
            $s = $row['source'];
            $m = $row['mapped'];
            $img = $m['front_image'] ? '<img src="' . e($m['front_image']) . '" loading="lazy">' : '<div class="noimg">no image</div>';
            $gallery = count($m['other_images']) + ($m['front_image'] ? 1 : 0);
            $zar = $s['price'] !== null ? 'R ' . number_format((float) $s['price']) : 'POA';
            $usd = $m['price'] !== null ? '$' . number_format((float) $m['price']) : '<span class="enq">Enquire</span>';
            $specBits = array_filter([
                ($m['seats'] ?? null) ? $m['seats'] . ' seats' : null,
                ($m['doors'] ?? null) ? $m['doors'] . ' doors' : null,
                $m['drive_type'] ?? null,
                ($m['engine_cc'] ?? null) ? $m['engine_cc'] . 'cc' : null,
                $m['color'] ?? null,
            ]);
            $rows .= '<tr>'
                . '<td class="n">' . ($i + 1) . '</td>'
                . '<td>' . $img . '</td>'
                . '<td><a href="' . e($s['product_link']) . '" target="_blank" rel="noopener">' . e($m['title']) . '</a>'
                . '<div class="sub">' . e($s['dealer'] ?? '') . '</div>'
                . '<div class="src"><a href="' . e($s['product_link']) . '" target="_blank" rel="noopener">↗ view on AutoTrader</a></div></td>'
                . '<td class="price"><b>' . $usd . '</b><div class="sub">' . $zar . '</div></td>'
                . '<td>' . e($m['condition'] ?? '—') . '</td>'
                . '<td>' . e((string) ($m['year'] ?? '—')) . '</td>'
                . '<td>' . ($m['mileage_km'] !== null ? number_format($m['mileage_km']) . ' km' : '—') . '</td>'
                . '<td>' . e($m['fuel'] ?? '—') . ' / ' . e($m['transmission'] ?? '—') . '</td>'
                . '<td class="specs">' . ($specBits ? e(implode(' · ', $specBits)) : '<span class="sub">search-only</span>') . '</td>'
                . '<td class="ph"><b>' . $gallery . '</b>' . ($s['image_count'] ?? null ? ' / ' . $s['image_count'] . ' avail' : '') . '</td>'
                . '</tr>';
        }

        $mode = $this->option('deep') ? 'deep — full detail pages (seats, doors, engine, whole gallery)' : 'search-only (add --deep for full specs + gallery)';

        return '<!doctype html><meta charset="utf-8"><title>AutoTrader ZA scrape preview</title><style>'
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
            . '.price b{font-size:15px;color:#0b1e3b}.enq{color:#b5591a}.specs{max-width:200px;color:#33445e}'
            . '.ph b{color:#1f8f57}.n{color:#b3bece;font-variant-numeric:tabular-nums}'
            . '</style><div class="wrap"><h1>AutoTrader ZA → SupremeMotors · ' . count($this->reportRows) . ' products</h1>'
            . '<p class="lede">Mode: <b>' . e($mode) . '</b>. Click <b>↗ view on AutoTrader</b> to open the live listing and compare against our stored row side by side. Prices converted to <b>USD</b> at ' . ($rate > 0 ? number_format($rate, 4) . ' (≈ R' . number_format(1 / $rate, 1) . '/$)' : 'raw ZAR') . ' — cards show the real price, not Enquire. Images point at the sm-autotrader Bunny CDN; originals kept in *_source.</p>'
            . '<table><tr><th>#</th><th>Image (CDN)</th><th>Title / source link</th><th>Price USD / ZAR</th><th>Condition</th><th>Year</th><th>Mileage</th><th>Fuel / Gearbox</th><th>Detail specs</th><th>Photos</th></tr>'
            . $rows . '</table></div>';
    }
}
