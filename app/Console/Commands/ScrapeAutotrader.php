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
        {--deep : Also fetch each detail page for the full gallery + spec sheet (25x slower)}
        {--dry-run : Parse and map but write nothing to the database}
        {--report= : Write an HTML source-vs-database comparison sheet to this path}
        {--refresh : Re-scrape listings that already exist in the database}
        {--delay-ms=2500 : Base pause between requests (jittered), keeps us polite}
        {--proxy-file= : Newline-delimited proxy list (host:port or user:pass@host:port); rotates + fails over on ban}
        {--usd-rate=0 : Convert ZAR prices to USD at this rate (0 = store raw ZAR)}';

    protected $description = 'Scrape autotrader.co.za into products with Bunny CDN images (search-first, resumable, proxy-rotating)';

    private const CDN_HOST = 'sm-autotrader.b-cdn.net';
    private const ORIGIN_HOST = AutotraderParser::IMAGE_HOST;
    private const SEARCH_URL = AutotraderParser::BASE . '/cars-for-sale';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private AutotraderParser $parser;

    private int $upserted = 0;

    private array $reportRows = [];

    private ?int $carsCategoryId = null;

    private array $makeIds = [];

    /** @var string[] */
    private array $proxies = [];

    private int $proxyIdx = 0;

    public function handle(AutotraderParser $parser): int
    {
        $this->parser = $parser;
        $this->upserted = 0;
        $this->reportRows = [];
        $this->carsCategoryId = null;
        $this->makeIds = [];
        $this->loadProxies();

        $stateDir = config('cdn.state_dir');
        @mkdir($stateDir, 0777, true);
        $cursorFile = $stateDir . '/autotrader.cursor';

        $page = $this->option('start-page') !== null
            ? max(1, (int) $this->option('start-page'))
            : (is_file($cursorFile) ? ((int) file_get_contents($cursorFile)) + 1 : 1);

        $maxPages = (int) $this->option('max-pages');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $deep = (bool) $this->option('deep');
        $pagesDone = 0;

        $this->info(($dryRun ? '[dry-run] ' : '') . ($deep ? '[deep] ' : '[search] ')
            . 'starting at page ' . $page
            . ($this->proxies ? ' via ' . count($this->proxies) . ' proxies' : ''));

        while (true) {
            $html = $this->fetch(self::SEARCH_URL . '?pagenumber=' . $page);
            if ($html === null) {
                $this->warn("search page {$page} unfetchable after retries — stopping so the cursor stays honest");

                return self::FAILURE;
            }

            $search = $this->parser->parseSearchListings($html);
            if (!$search['listings']) {
                $this->info("page {$page} has no listings — end of inventory reached");
                break;
            }

            foreach ($search['listings'] as $tile) {
                if ($limit > 0 && $this->upserted >= $limit) {
                    break 2;
                }
                $this->processTile($tile, $dryRun, $deep);
            }

            if (!$dryRun) {
                file_put_contents($cursorFile, (string) $page);
            }
            $pagesDone++;
            $this->info("page {$page}/" . ($search['last_page'] ?? '?')
                . ' done — ' . $this->upserted . ' products banked'
                . ($search['total'] ? ' (of ~' . number_format($search['total']) . ')' : ''));

            if ($search['last_page'] !== null && $page >= $search['last_page']) {
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

    /** @param array<string,mixed> $tile */
    private function processTile(array $tile, bool $dryRun, bool $deep): void
    {
        $url = $tile['product_link'];

        if (!$this->option('refresh') && !$dryRun && Products::where('product_link', $url)->exists()) {
            return;
        }

        $data = $tile;
        if ($deep) {
            $detailHtml = $this->fetch($url);
            if ($detailHtml !== null) {
                $detail = $this->parser->parseDetailPage($detailHtml, $url);
                if ($detail !== null) {
                    // detail page wins on gallery + specs; keep search fields it lacks
                    $data = array_merge($tile, array_filter($detail, fn ($v) => $v !== null && $v !== []));
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

        $this->upserted++;
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
            'category_id' => $this->carsCategoryId(),
            'make_id' => !empty($data['make']) ? $this->makeId($data['make']) : null,
            'price' => $price,
            'country' => $data['country'] ?? 'South Africa',
            'website' => 'autotrader',
            'body_style' => $data['body_style'] ?? null,
            'product_link' => $data['product_link'],
            'front_image' => $images[0] ?? null,
            'front_image_source' => $sourceImages[0] ?? null,
            'other_images' => array_slice($images, 1),
            'other_images_source' => json_encode(array_slice($sourceImages, 1)),
            'product_details' => $data['product_details'] ?? '',
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

    private function makeId(string $name): int
    {
        return $this->makeIds[$name] ??= Categories::firstOrCreate(
            ['cat_title' => $name, 'type' => 'make'],
        )->id;
    }

    private function loadProxies(): void
    {
        $this->proxies = [];
        $this->proxyIdx = 0;
        $file = $this->option('proxy-file');
        if ($file && is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line !== '' && !str_starts_with($line, '#')) {
                    $this->proxies[] = $line;
                }
            }
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
        $this->warn("skip {$url}: {$reason}");
        file_put_contents(
            config('cdn.state_dir') . '/autotrader-failures.log',
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
            $img = $m['front_image'] ? '<img src="' . e($m['front_image']) . '" loading="lazy">' : '';
            $gallery = count($m['other_images']) + ($m['front_image'] ? 1 : 0);
            $priceCell = $s['price'] !== null ? 'R ' . number_format((float) $s['price']) : 'POA / Enquire';
            $rows .= '<tr>'
                . '<td class="n">' . ($i + 1) . '</td>'
                . '<td>' . $img . '</td>'
                . '<td><a href="' . e($s['product_link']) . '" target="_blank">' . e($s['title']) . '</a>'
                . '<div class="sub">' . e($s['dealer'] ?? '') . '</div></td>'
                . '<td>' . $priceCell . '</td>'
                . '<td>' . e($m['condition'] ?? '—') . '</td>'
                . '<td>' . e((string) ($m['year'] ?? '—')) . '</td>'
                . '<td>' . ($m['mileage_km'] !== null ? number_format($m['mileage_km']) . ' km' : '—') . '</td>'
                . '<td>' . e($m['fuel'] ?? '—') . ' / ' . e($m['transmission'] ?? '—') . '</td>'
                . '<td>' . $gallery . ($s['image_count'] ?? null ? ' / ' . $s['image_count'] : '') . '</td>'
                . '<td class="url">' . e($m['front_image'] ?? '') . '</td>'
                . '</tr>';
        }

        return '<!doctype html><meta charset="utf-8"><title>AutoTrader ZA scrape preview</title><style>'
            . 'body{font-family:Segoe UI,sans-serif;background:#f4f6f9;margin:24px;color:#0b1e3b}'
            . 'h1{font-size:22px}table{border-collapse:collapse;width:100%;background:#fff;font-size:13px}'
            . 'th,td{border:1px solid #e3e8f0;padding:8px;text-align:left;vertical-align:top}'
            . 'th{background:#0b1e3b;color:#fff;position:sticky;top:0}'
            . 'img{width:130px;height:98px;object-fit:cover;border-radius:6px}'
            . '.n{color:#8494ab}.sub{color:#8494ab;font-size:11px}.url{font-size:10px;word-break:break-all;max-width:220px}'
            . '</style><h1>AutoTrader ZA → SupremeMotors mapping preview (' . count($this->reportRows) . ' products)</h1>'
            . '<p>Captured from search-page data (no detail fetch). Prices stay in ZAR unless --usd-rate is set; POA listings and the "autotrader" website (not price-visible) render as <strong>Enquire</strong> on cards. Images point at the sm-autotrader Bunny pull zone; originals live in *_source. Photos column shows captured / total-available — run with --deep to pull the full gallery.</p>'
            . '<table><tr><th>#</th><th>Front image (CDN)</th><th>Title / dealer</th><th>Price</th><th>Condition</th><th>Year</th><th>Mileage</th><th>Fuel / Gearbox</th><th>Photos</th><th>CDN front URL</th></tr>'
            . $rows . '</table>';
    }
}
