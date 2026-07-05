<?php

namespace App\Console\Commands;

use App\Models\Categories;
use App\Models\Products;
use App\Services\AutotraderParser;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Crash-safe autotrader.co.za scraper. The page cursor is checkpointed after
 * every search page and each listing is upserted individually, so a net drop,
 * kill, or power loss never loses banked work — rerunning resumes where it
 * stopped. Images are rewritten onto the sm-autotrader Bunny pull zone
 * (origin img.autotrader.co.za) with originals kept in *_source, matching the
 * CDN swap convention used by the other sources.
 */
class ScrapeAutotrader extends Command
{
    protected $signature = 'scrape:autotrader
        {--start-page= : Override the resume cursor and start from this search page}
        {--max-pages=0 : Stop after this many search pages (0 = all)}
        {--limit=0 : Stop after upserting this many products (0 = all)}
        {--dry-run : Parse and map but write nothing to the database}
        {--report= : Write an HTML source-vs-database comparison sheet to this path}
        {--refresh : Re-fetch listings that already exist in the database}
        {--delay-ms=800 : Pause between requests, keeps us polite to the origin}
        {--usd-rate=0 : Convert ZAR prices to USD at this rate (0 = store raw ZAR)}';

    protected $description = 'Scrape autotrader.co.za listings into products with Bunny CDN image URLs (resumable, re-runnable, outage-proof)';

    private const CDN_HOST = 'sm-autotrader.b-cdn.net';
    private const ORIGIN_HOST = 'img.autotrader.co.za';
    private const SEARCH_URL = AutotraderParser::BASE . '/cars-for-sale';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private AutotraderParser $parser;

    private int $upserted = 0;

    private array $reportRows = [];

    private ?int $carsCategoryId = null;

    private array $makeIds = [];

    public function handle(AutotraderParser $parser): int
    {
        $this->parser = $parser;
        $this->upserted = 0;
        $this->reportRows = [];
        $this->carsCategoryId = null;
        $this->makeIds = [];

        $stateDir = config('cdn.state_dir');
        @mkdir($stateDir, 0777, true);
        $cursorFile = $stateDir . '/autotrader.cursor';

        $page = $this->option('start-page') !== null
            ? max(1, (int) $this->option('start-page'))
            : (is_file($cursorFile) ? ((int) file_get_contents($cursorFile)) + 1 : 1);

        $maxPages = (int) $this->option('max-pages');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $pagesDone = 0;

        $this->info(($dryRun ? '[dry-run] ' : '') . "starting at search page {$page}");

        while (true) {
            $html = $this->fetch(self::SEARCH_URL . '?pagenumber=' . $page);
            if ($html === null) {
                $this->warn("search page {$page} unfetchable after retries — stopping so the cursor stays honest");

                return self::FAILURE;
            }

            $search = $this->parser->parseSearchPage($html);
            if (!$search['listing_urls']) {
                $this->info("page {$page} has no listings — end of inventory reached");
                break;
            }

            foreach ($search['listing_urls'] as $url) {
                if ($limit > 0 && $this->upserted >= $limit) {
                    break 2;
                }
                $this->processListing($url, $dryRun);
            }

            if (!$dryRun) {
                file_put_contents($cursorFile, (string) $page);
            }
            $pagesDone++;
            $this->info("page {$page}/" . ($search['last_page'] ?? '?') . " done — {$this->upserted} products banked");

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

    private function processListing(string $url, bool $dryRun): void
    {
        if (!$this->option('refresh') && !$dryRun && Products::where('product_link', $url)->exists()) {
            return;
        }

        $html = $this->fetch($url);
        if ($html === null) {
            $this->logFailure($url, 'unfetchable after retries');

            return;
        }

        $data = $this->parser->parseDetailPage($html, $url);
        if ($data === null) {
            $this->logFailure($url, 'no listing data on page');

            return;
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
        $images = array_map($this->toCdn(...), $data['images']);
        $rate = (float) $this->option('usd-rate');
        $price = $data['price'] !== null && $rate > 0
            ? round($data['price'] * $rate, 2)
            : $data['price'];

        return [
            'title' => mb_substr($data['title'], 0, 500),
            'model' => $data['model'] ? mb_substr($data['model'], 0, 100) : null,
            'year' => $data['year'],
            'engine_cc' => $data['engine_cc'],
            'mileage_km' => $data['mileage_km'],
            'fuel' => $data['fuel'],
            'transmission' => $data['transmission'],
            'condition' => $data['condition'],
            'color' => $data['color'],
            'steering' => $data['steering'],
            'seats' => $data['seats'],
            'doors' => $data['doors'],
            'drive_type' => $data['drive_type'],
            'category_id' => $this->carsCategoryId(),
            'make_id' => $data['make'] ? $this->makeId($data['make']) : null,
            'price' => $price,
            'country' => $data['country'],
            'website' => $data['website'],
            'body_style' => $data['body_style'],
            'product_link' => $data['product_link'],
            'front_image' => $images[0] ?? null,
            'front_image_source' => $data['images'][0] ?? null,
            // other_images carries an array cast; the *_source column does not
            'other_images' => array_slice($images, 1),
            'other_images_source' => json_encode(array_slice($data['images'], 1)),
            'product_details' => $data['product_details'],
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

    /**
     * Fetch with two layers of resilience: quick retries with backoff for
     * transient blips, then an outage loop that waits a minute between rounds
     * so a long net drop pauses the scrape instead of failing it.
     */
    private function fetch(string $url): ?string
    {
        $delayMs = max(0, (int) $this->option('delay-ms'));
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        for ($round = 1; ; $round++) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                try {
                    $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                        ->timeout(30)
                        ->get($url);

                    if ($response->successful()) {
                        return $response->body();
                    }
                    if (in_array($response->status(), [404, 410], true)) {
                        return null; // listing withdrawn — a fact, not an outage
                    }
                    if (in_array($response->status(), [429, 503], true)) {
                        sleep(min(90, 15 * $attempt)); // throttled / bot-challenged: back off harder
                        continue;
                    }
                } catch (\Throwable) {
                    // connection error — fall through to backoff
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
            $rows .= '<tr>'
                . '<td class="n">' . ($i + 1) . '</td>'
                . '<td>' . $img . '</td>'
                . '<td><a href="' . e($s['product_link']) . '" target="_blank">' . e($s['title']) . '</a>'
                . '<div class="sub">' . e($s['dealer'] ?? '') . '</div></td>'
                . '<td>R ' . number_format((float) $s['price']) . '</td>'
                . '<td>' . e($m['condition'] ?? '') . '</td>'
                . '<td>' . e((string) ($m['year'] ?? '—')) . '</td>'
                . '<td>' . ($m['mileage_km'] !== null ? number_format($m['mileage_km']) . ' km' : '—') . '</td>'
                . '<td>' . e($m['fuel'] ?? '—') . ' / ' . e($m['transmission'] ?? '—') . '</td>'
                . '<td>' . e($m['body_style'] ?? '—') . '</td>'
                . '<td>' . $gallery . '</td>'
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
            . '<p>Prices stay in ZAR unless --usd-rate is set; website "autotrader" is not in the price-visible list so cards show <strong>Enquire</strong>. Images point at the sm-autotrader Bunny pull zone; originals are preserved in *_source columns.</p>'
            . '<table><tr><th>#</th><th>Front image (CDN)</th><th>Title / dealer</th><th>Price (source)</th><th>Condition</th><th>Year</th><th>Mileage</th><th>Fuel / Gearbox</th><th>Body</th><th>Photos</th><th>CDN front URL</th></tr>'
            . $rows . '</table>';
    }
}
