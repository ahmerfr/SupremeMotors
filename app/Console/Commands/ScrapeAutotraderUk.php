<?php

namespace App\Console\Commands;

use App\Models\Categories;
use App\Models\Products;
use App\Services\AutotraderUkDetailParser;
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
 * TRANSPORT: every live request shells out to the Windows curl.exe (Schannel
 * TLS). PHP's own libcurl is OpenSSL-built and its TLS fingerprint is flagged by
 * Cloudflare Bot Management -> 403 on every call; curl.exe's Schannel
 * fingerprint passes. Concurrency (a pool of curl.exe processes) is tolerated
 * from one home IP with the __cf_bm cookie; batching (~10 search-ops per gateway
 * POST) cuts the request count. Images are rewritten onto the sm-autotraderuk
 * Bunny pull zone with originals kept in *_source.
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
        {--enrich : PHASE 2 — fetch car-details HTML for every specifications-NULL row and fill full gallery + specs (ignores Phase-1 options)}
        {--pool=8 : Concurrent requests per Guzzle Pool (Phase-1 batched POSTs / Phase-2 detail GETs); 8 is proven safe, 10-12 cautiously}
        {--batch=10 : Search pages per gateway POST (the gateway accepts ~10-15 ops; 10 is the safe ceiling)}
        {--enrich-limit=0 : PHASE 2 — stop after enriching this many products (0 = all)}
        {--dry-run : Parse and map but write nothing to the database}
        {--report= : Write an HTML source-vs-database comparison sheet to this path}
        {--curl-bin= : Override the Schannel curl.exe path (default C:\Windows\System32\curl.exe)}
        {--delay-ms=2500 : Base pause between gateway rounds (jittered 1x..1.4x)}';

    protected $description = 'Scrape autotrader.co.uk into products with Bunny CDN images (two-phase: batched+concurrent search, then concurrent detail-HTML enrich; resumable, sharded)';

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

    private AutotraderUkDetailParser $detailParser;

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

    /**
     * Windows-curl.exe transport (Schannel TLS). PHP's own libcurl uses OpenSSL,
     * whose TLS fingerprint Cloudflare Bot Management flags -> 403 on every call.
     * curl.exe's Schannel fingerprint passes, so ALL live UK HTTP goes through it.
     */
    private ?\App\Services\SchannelCurl $curl = null;

    /** curl cookie-jar FILE — holds __cf_bm across the handshake + gateway calls */
    private string $jarFile = '';

    public function handle(AutotraderUkParser $parser, AutotraderUkDetailParser $detailParser): int
    {
        $this->parser = $parser;
        $this->detailParser = $detailParser;
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

        // live transport: Schannel curl.exe + a per-run cookie jar file. Tests
        // stay on Http::fake (usesCurl() is false under runningUnitTests()).
        if ($this->usesCurl()) {
            $this->curl = new \App\Services\SchannelCurl($this->curlBin(), $stateDir);
            $this->jarFile = $stateDir . '/autotraderuk-cookies-' . getmypid() . '.jar';
            @unlink($this->jarFile);
        }

        $this->dryRun = (bool) $this->option('dry-run');

        // PHASE 2 — enrich the specifications-NULL rows with full detail data
        if ($this->option('enrich')) {
            return $this->enrich($stateDir);
        }

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
        $pagesDone = 0;

        $this->info(($this->dryRun ? '[dry-run] ' : '') . 'autotraderuk '
            . ($shard ? "shard {$shard} " : '') . 'starting at page ' . $page
            . ($this->option('make') ? ' make=' . $this->option('make') : '')
            . ($this->priceLabel() ? ' price=' . $this->priceLabel() : ''));

        // how many pages to pull per round: min(remaining allowance, batch*pool).
        // Each round issues `pool` concurrent gateway POSTs, each POST carrying
        // `batch` page-ops — the proven fast path (268 cars in one ~2s POST).
        $batch = max(1, (int) $this->option('batch'));
        $pool = max(1, (int) $this->option('pool'));
        $lastPage = self::PAGE_CAP;

        while ($page <= self::PAGE_CAP) {
            // cap this round to the max-pages allowance so --max-pages=1 fetches
            // exactly page 1 (keeps the resumable cursor honest + tests green)
            $roundPages = $batch * $pool;
            if ($maxPages > 0) {
                $roundPages = min($roundPages, $maxPages - $pagesDone);
            }
            $roundPages = min($roundPages, self::PAGE_CAP - $page + 1, $lastPage - $page + 1);
            if ($roundPages <= 0) {
                break;
            }

            $roundStart = $page;
            $pages = range($page, $page + $roundPages - 1);
            $payloads = $this->fetchPages($pages, $batch, $pool);

            $stop = false;   // hit the product limit
            $ended = false;  // ran off the end of the filter set (empty page)
            $highestBanked = $page - 1;

            foreach ($pages as $p) {
                $payload = $payloads[$p] ?? null;
                if ($payload === null) {
                    $this->warn("page {$p} unfetchable after retries — stopping so the cursor stays honest");

                    return self::FAILURE;
                }

                $search = $this->parser->parseSearchResponse($payload);
                if (!$search['listings']) {
                    $this->info("page {$p} has no listings — end of this filter set reached");
                    $ended = true;
                    break;
                }

                foreach ($search['listings'] as $listing) {
                    if ($limit > 0 && $this->upserted >= $limit) {
                        $stop = true;
                        break;
                    }
                    $this->bankListing($listing);
                }

                $highestBanked = $p;
                $lastPage = min(self::PAGE_CAP, $search['last_page'] ?? self::PAGE_CAP);
                $lastTotal = $search['total'];

                if ($stop) {
                    break;
                }
            }

            $bankedThisRound = max(0, $highestBanked - $roundStart + 1);
            $pagesDone += $bankedThisRound;
            $page = $highestBanked + 1;

            if ($bankedThisRound > 0 && !$this->dryRun) {
                file_put_contents($cursorFile, (string) $highestBanked);
            }
            $this->writeProgress($stateDir, $suffix, $shard, $highestBanked, $lastPage, $lastTotal ?? null);
            $this->info("through page {$highestBanked}/{$lastPage} — {$this->upserted} products banked"
                . (isset($lastTotal) && $lastTotal ? ' (filter set ~' . number_format($lastTotal) . ' cars)' : ''));

            if ($stop) {
                break;
            }
            if ($ended || $page > $lastPage) {
                if (isset($lastTotal) && $lastTotal !== null && $lastTotal > self::PAGE_CAP * 20) {
                    $this->warn("filter set has ~{$lastTotal} cars but the gateway caps at page "
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
     * PHASE 1 fetch: pull `$pages` and return [page => single-result payload].
     *
     * Live path: chunk the pages into `$batch`-op gateway POSTs and run `$pool`
     * of those POSTs concurrently through a Guzzle Pool (direct, no proxies — the
     * UK Cloudflare tolerates concurrency from one IP with the cookie). Each POST
     * answers with one result per op, which we map back to its page.
     *
     * Test/degenerate path: with the Http fake, a single page, or pool=1 we take
     * the polite sequential fetcher so the existing Http::fake + assertions hold.
     *
     * @param  int[]  $pages
     * @return array<int,array<mixed>|null>
     */
    private function fetchPages(array $pages, int $batch, int $pool): array
    {
        if (app()->runningUnitTests() || $pool <= 1) {
            $out = [];
            foreach (array_chunk($pages, $batch) as $chunk) {
                $payload = $this->fetchBatch($chunk);
                foreach ($chunk as $i => $p) {
                    $out[$p] = $payload === null ? null : ($payload[$i] ?? null);
                }
            }

            return $out;
        }

        return $this->fetchBatchesConcurrently($pages, $batch, $pool);
    }

    /**
     * Fetch one batch of pages in a single gateway POST, returning the decoded
     * JSON ARRAY (one result per requested page), or null after exhausting
     * retries. Handles the Cloudflare handshake, cookie refresh, jittered pacing,
     * and 403 -> re-handshake.
     *
     * @param  int[]  $pages
     * @return array<mixed>|null
     */
    private function fetchBatch(array $pages): ?array
    {
        $this->pace();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->ensureSession($attempt > 1);

            try {
                $response = Http::withHeaders($this->gatewayHeaders())
                    ->withOptions(['cookies' => $this->cookieJar()])
                    ->timeout(30)
                    ->post(self::GATEWAY_URL, $this->buildBody($pages));

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

    /**
     * Run many batched gateway POSTs concurrently through a rolling Guzzle Pool
     * (direct, no proxies). Pages are chunked into `$batch`-op POSTs; up to
     * `$pool` POSTs are in flight at once. Chunks that come back 403/429/503 or
     * error are retried across rounds (with a cookie refresh on a 403), so a
     * transient block doesn't lose pages. Returns [page => single-result payload].
     *
     * @param  int[]  $pages
     * @return array<int,array<mixed>|null>
     */
    private function fetchBatchesConcurrently(array $pages, int $batch, int $pool): array
    {
        $this->pace();
        $this->ensureSession();

        $out = [];
        $pendingChunks = array_map('array_values', array_chunk($pages, $batch));
        $headers = $this->gatewayHeaders();

        for ($round = 1; $round <= 5 && $pendingChunks !== []; $round++) {
            // one curl.exe wave: chunk index => a batched gateway POST
            $requests = [];
            foreach ($pendingChunks as $ci => $chunk) {
                $requests[$ci] = [
                    'method' => 'POST',
                    'url' => self::GATEWAY_URL,
                    'headers' => $headers,
                    'body' => json_encode($this->buildBody($chunk)),
                ];
            }

            $responses = $this->curl->parallel($requests, $this->jarFile, $pool, 40);

            $retry = [];
            $needsCookieRefresh = false;
            foreach ($pendingChunks as $ci => $chunk) {
                [$code, $bodyStr] = $responses[$ci] ?? [0, ''];
                if ($code === 403 || $code === 429 || $code === 503 || $code === 0) {
                    $needsCookieRefresh = $needsCookieRefresh || $code === 403 || $code === 0;
                    $retry[] = $chunk;

                    continue;
                }
                $json = json_decode($bodyStr, true);
                if (!is_array($json)) {
                    $retry[] = $chunk;

                    continue;
                }
                foreach ($chunk as $i => $p) {
                    $out[$p] = $json[$i] ?? null;
                }
            }

            $pendingChunks = $retry;
            if ($pendingChunks !== [] && $round < 5) {
                if ($needsCookieRefresh) {
                    $this->cookieMintedAt = 0;
                    $this->ensureSession(true);
                }
                sleep(min(30, 5 * $round));
            }
        }

        // pages in chunks that never came through -> null (caller stops honestly)
        foreach ($pendingChunks as $chunk) {
            foreach ($chunk as $p) {
                $out[$p] = null;
            }
            $this->failures++;
        }

        return $out;
    }

    /** the current cookie jar folded into a single Cookie: header string */
    private function cookieHeader(): string
    {
        $bits = [];
        foreach ($this->cookies as $name => $value) {
            $bits[] = $name . '=' . $value;
        }

        return implode('; ', $bits);
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
        $minted = $this->usesCurl() ? is_file($this->jarFile) : (bool) $this->cookies;
        $stale = (time() - $this->cookieMintedAt) > self::COOKIE_TTL;
        if (!$force && $minted && !$stale) {
            return;
        }

        // LIVE: mint/refresh __cf_bm by GETting the homepage through curl.exe;
        // curl writes the cookie into the shared jar file (-c) for reuse.
        if ($this->usesCurl()) {
            [$status] = $this->curl->request('GET', self::HOME_URL, [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-GB,en;q=0.9',
            ], null, $this->jarFile, 30);
            // even a 403 challenge page still sets __cf_bm in the jar, which the
            // gateway then accepts — so only the jar's existence gates us here
            if (is_file($this->jarFile)) {
                $this->cookieMintedAt = time();
            }

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

    /** live runs use curl.exe (Schannel); tests stay on the fakeable Http client */
    private function usesCurl(): bool
    {
        return !app()->runningUnitTests();
    }

    /** the Schannel curl binary (overridable via --curl-bin for odd installs) */
    private function curlBin(): string
    {
        $opt = $this->option('curl-bin');

        return is_string($opt) && $opt !== '' ? $opt : 'C:\\Windows\\System32\\curl.exe';
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
     * Headers for a car-details HTML GET. Cloudflare challenges a bare GET (a
     * bot tell) — a real browser navigation carries Sec-Fetch-* +
     * Upgrade-Insecure-Requests + an html Accept, so we send the full set or the
     * page 403s even with a valid __cf_bm.
     *
     * @return array<string,string>
     */
    private function detailHeaders(): array
    {
        return [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-GB,en;q=0.9',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-User' => '?1',
            'Sec-Fetch-Dest' => 'document',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'Referer' => AutotraderUkParser::BASE . '/car-search',
        ];
    }

    /**
     * The JSON-ARRAY gateway body for one or more pages. The gateway accepts a
     * BATCH of operations in one POST (proven ~10 ops safe), so each requested
     * page becomes one op — the whole array answers with one result per op.
     *
     * @param  int[]  $pages
     * @return array<int,array<string,mixed>>
     */
    private function buildBody(array $pages): array
    {
        return array_map($this->buildOp(...), $pages);
    }

    /**
     * A single SearchResultsListingsGridQuery op. `channel` is a top-level var;
     * the postcode + `price_search_type:total` filters are required, then the
     * optional make / price shard filters.
     *
     * @return array<string,mixed>
     */
    private function buildOp(int $page): array
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

        return [
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
        ];
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

    /**
     * PHASE 2 — enrich every specifications-NULL autotraderuk row with the full
     * car-details data (complete gallery + fuel/transmission/body/engine/doors/
     * seats/colour). Fetches the detail HTML concurrently (Guzzle Pool, cookie,
     * no proxies), parses it, and UPDATEs the row in place, merging the detail
     * over the search-tier row. Loops until no NULL-specifications row remains;
     * withdrawn listings (404/410) are deleted since they can never complete.
     */
    private function enrich(string $stateDir): int
    {
        $pool = max(1, (int) $this->option('pool'));
        $limit = (int) $this->option('enrich-limit');
        $doneMarker = $stateDir . '/autotraderuk-fill.done';
        @unlink($doneMarker);

        $this->info(($this->dryRun ? '[dry-run] ' : '') . "autotraderuk enrich starting (pool={$pool})");

        for ($round = 1; ; $round++) {
            $remaining = Products::where('website', 'autotraderuk')->whereNull('specifications')->count();
            file_put_contents(
                $stateDir . '/autotraderuk-fill-progress.json',
                json_encode([
                    'source' => 'autotraderuk',
                    'incomplete_remaining' => $remaining,
                    'enriched' => $this->upserted,
                    'updated_at' => date('c'),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            if ($remaining === 0) {
                if (!$this->dryRun) {
                    file_put_contents($doneMarker, now()->toDateTimeString() . "\n");
                }
                $this->info('enrich: every autotraderuk product has full detail');

                return self::SUCCESS;
            }

            if ($limit > 0 && $this->upserted >= $limit) {
                $this->info("enrich: hit --enrich-limit={$limit}, stopping");

                return self::SUCCESS;
            }

            $take = 300;
            if ($limit > 0) {
                $take = min($take, $limit - $this->upserted);
            }
            /** @var array<int,\App\Models\Products> $products */
            $products = Products::where('website', 'autotraderuk')->whereNull('specifications')
                ->limit($take)->get(['id', 'product_link'])->all();
            $links = array_map(fn ($p) => $p->product_link, $products);
            $this->info("enrich round {$round}: {$remaining} missing detail — fetching " . count($links)
                . " (pool={$pool})");

            $htmlByUrl = $this->fetchDetailBatch($links, $pool);

            $filled = 0;
            $failed = [];
            foreach ($links as $url) {
                $html = $htmlByUrl[$url] ?? null;
                $detail = $html !== null ? $this->detailParser->parseDetail($html, $url) : null;

                if ($detail !== null && !empty($detail['images'])) {
                    if (!$this->dryRun) {
                        $existing = Products::where('product_link', $url)->first();
                        // detail wins on the fields it carries; keep the search-tier
                        // values (power_hp, product_details, price…) it doesn't touch
                        $merged = array_merge(
                            $existing ? $this->existingAsData($existing) : [],
                            $detail
                        );
                        Products::where('product_link', $url)->update($this->mapToProduct($merged));
                    }
                    $filled++;
                    $this->upserted++;
                    $this->imagesScraped += count($detail['images']);
                } else {
                    $failed[] = $url;
                }
            }
            $this->info("enrich round {$round}: filled {$filled}, still failing " . count($failed));

            // dry-run persists nothing, so the specifications-NULL set never
            // shrinks — do exactly one round and stop (never loop forever)
            if ($this->dryRun) {
                $this->info("[dry-run] enrich: {$filled} rows would be filled, " . count($failed) . ' would fail');

                return self::SUCCESS;
            }

            if ($filled === 0) {
                // nothing came through — delete any genuinely withdrawn (404/410),
                // then hand back so a fresh cookie/retry picks the rest up next run
                if (!$this->dryRun) {
                    $this->pruneWithdrawn(array_slice($failed, 0, 25));
                }

                return self::SUCCESS;
            }
        }
    }

    /**
     * Fetch many car-details pages CONCURRENTLY, returning [url => html|null].
     * Direct (no proxies) rolling Guzzle Pool with the Cloudflare cookie; the UK
     * site tolerates concurrency from one IP. 404/410 -> null (withdrawn). Ban/
     * timeout chunks retry across rounds with a cookie refresh. The Http-fake
     * test path uses the polite sequential fetcher.
     *
     * @param  string[]  $urls
     * @return array<string,string|null>
     */
    private function fetchDetailBatch(array $urls, int $pool): array
    {
        if (app()->runningUnitTests() || $pool <= 1) {
            $out = [];
            foreach ($urls as $url) {
                $out[$url] = $this->fetchDetail($url);
            }

            return $out;
        }

        $this->ensureSession();
        $results = [];
        $pending = array_values($urls);
        $headers = $this->detailHeaders();

        for ($round = 1; $round <= 5 && $pending !== []; $round++) {
            // one curl.exe wave of detail-page GETs, keyed by url
            $requests = [];
            foreach ($pending as $url) {
                $requests[$url] = ['method' => 'GET', 'url' => $url, 'headers' => $headers];
            }

            $responses = $this->curl->parallel($requests, $this->jarFile, $pool, 40);

            $retry = [];
            $needsCookieRefresh = false;
            foreach ($pending as $url) {
                [$code, $bodyStr] = $responses[$url] ?? [0, ''];
                if ($code >= 200 && $code < 300) {
                    $results[$url] = $bodyStr;
                } elseif (in_array($code, [404, 410], true)) {
                    $results[$url] = null; // withdrawn
                } else {
                    $needsCookieRefresh = $needsCookieRefresh || $code === 403 || $code === 0;
                    $retry[] = $url;
                }
            }

            $pending = $retry;
            if ($pending !== [] && $round < 5) {
                if ($needsCookieRefresh) {
                    $this->cookieMintedAt = 0;
                    $this->ensureSession(true);
                }
                sleep(min(30, 3 * $round));
            }
        }

        foreach ($pending as $url) {
            $results[$url] = null;
            $this->logFailure($url, 'detail unfetchable after pooled retries');
        }

        return $results;
    }

    /** polite single-detail fetch (test/degenerate path), with 404/410 -> null */
    private function fetchDetail(string $url): ?string
    {
        $headers = $this->detailHeaders();
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->ensureSession($attempt > 1);
            try {
                if ($this->usesCurl()) {
                    [$status, $body] = $this->curl->request('GET', $url, $headers, null, $this->jarFile, 40);
                } else {
                    $response = Http::withHeaders($headers)
                        ->withOptions(['cookies' => $this->cookieJar()])->timeout(40)->get($url);
                    $status = $response->status();
                    $body = $response->body();
                }

                if (in_array($status, [404, 410], true)) {
                    return null; // withdrawn — a fact, not an outage
                }
                if ($status === 403 || $status === 429 || $status === 503) {
                    $this->cookies = [];
                    $this->cookieMintedAt = 0;
                    if (app()->runningUnitTests()) {
                        return null;
                    }
                    sleep(min(30, 5 * $attempt));

                    continue;
                }
                if ($status >= 200 && $status < 300) {
                    return $body;
                }
            } catch (\Throwable) {
                if (app()->runningUnitTests()) {
                    return null;
                }
            }
            if (!app()->runningUnitTests()) {
                sleep($attempt);
            }
        }

        return null;
    }

    /** delete products whose listing is gone from AutoTrader (404/410) */
    private function pruneWithdrawn(array $urls): void
    {
        foreach ($urls as $url) {
            try {
                if ($this->usesCurl()) {
                    $this->ensureSession();
                    [$code] = $this->curl->request('GET', $url, ['User-Agent' => self::USER_AGENT], null, $this->jarFile, 15);
                } else {
                    $code = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                        ->withOptions(['cookies' => $this->cookieJar()])->timeout(15)->get($url)->status();
                }
                if (in_array($code, [404, 410], true)) {
                    Products::where('product_link', $url)->delete();
                    $this->warn("enrich: deleted withdrawn listing {$url}");
                }
            } catch (\Throwable) {
            }
            if (!app()->runningUnitTests()) {
                usleep(400000); // gentle on the home IP
            }
        }
    }

    /**
     * Re-hydrate an existing product row into the loose array shape mapToProduct
     * consumes, so a Phase-2 detail merge keeps the search-tier fields (power_hp,
     * product_details, price, model…) the detail page doesn't carry.
     *
     * @return array<string,mixed>
     */
    private function existingAsData(\App\Models\Products $p): array
    {
        return array_filter([
            'title' => $p->title,
            'model' => $p->model,
            'year' => $p->year,
            'engine_cc' => $p->engine_cc,
            'mileage_km' => $p->mileage_km,
            'fuel' => $p->fuel,
            'transmission' => $p->transmission,
            'condition' => $p->condition,
            'color' => $p->color,
            'seats' => $p->seats,
            'doors' => $p->doors,
            'drive_type' => $p->drive_type,
            'power_hp' => $p->power_hp,
            'price' => $p->price,
            'body_style' => $p->body_style,
            'country' => $p->country,
            'product_link' => $p->product_link,
            'product_details' => $p->product_details,
        ], fn ($v) => $v !== null && $v !== '');
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
