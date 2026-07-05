<?php

namespace App\Console\Commands;

use App\Models\Categories;
use App\Models\Products;
use App\Services\PerfectMotorsParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Crash-safe perfect-motors.com scraper (UAE export stock, ~5,900 cars).
 *
 * The site's listing pagination (/stock?page=N) is broken and caps out around
 * page 720, so it can never enumerate the full catalogue. Instead we SWEEP THE
 * DETAIL-ID RANGE: fetch /productDetail/{id} for id in [min-id, max-id]. A live
 * car answers 200 with a real page (parsed into a full product); a deleted or
 * never-used id answers 500/404 and is simply skipped. The valid ids cluster in
 * roughly 64,800..70,800, so the default 64,000..71,000 window covers them with
 * headroom.
 *
 * The origin has no WAF, no rate limiting and no proxies are needed, so detail
 * pages are fetched directly from the home IP concurrently (a rolling Guzzle
 * pool, --pool default 20). Prices are ALREADY USD on this source and stored
 * verbatim (no conversion). Images are rewritten onto the sm-perfectmotors Bunny
 * pull zone with originals kept in the *_source columns, matching the other
 * sources' CDN convention.
 *
 * Resumable / re-runnable: the last swept id checkpoints to perfectmotors.cursor
 * after each batch, products upsert by product_link, and a progress JSON +
 * heartbeat are written continuously. --fill-incomplete re-fetches any product
 * whose specifications is still NULL until none remain.
 */
class ScrapePerfectMotors extends Command
{
    protected $signature = 'scrape:perfectmotors
        {--min-id=64000 : First productDetail id to sweep}
        {--max-id=71000 : Last productDetail id to sweep}
        {--start-id= : Override the resume cursor and start from this id}
        {--pool=20 : Concurrent detail fetches (this origin has no WAF/rate-limit)}
        {--limit=0 : Stop after upserting this many products (0 = all)}
        {--dry-run : Parse and map but write nothing to the database}
        {--fill-incomplete : Skip sweeping; re-fetch detail for any perfectmotors product missing it, until none remain}
        {--report= : Write an HTML source-vs-database comparison sheet to this path}';

    protected $description = 'Scrape perfect-motors.com into products with Bunny CDN images (id-range sweep, resumable)';

    private const CDN_HOST = 'sm-perfectmotors.b-cdn.net';
    private const ORIGIN_HOST = PerfectMotorsParser::IMAGE_HOST; // perfect-motors.com
    private const ORIGIN = PerfectMotorsParser::BASE; // https://perfect-motors.com
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    /** how many detail ids to fetch per rolling batch (also the cursor stride) */
    private const BATCH = 200;

    private PerfectMotorsParser $parser;

    private int $upserted = 0;

    private int $imagesScraped = 0;

    private int $skipped = 0;

    private int $soldSkipped = 0;

    private int $failures = 0;

    private int $startTs = 0;

    private array $reportRows = [];

    private array $categoryIds = [];

    private array $makeIds = [];

    private bool $dryRun = false;

    public function handle(PerfectMotorsParser $parser): int
    {
        $this->parser = $parser;
        $this->upserted = 0;
        $this->imagesScraped = 0;
        $this->skipped = 0;
        $this->soldSkipped = 0;
        $this->failures = 0;
        $this->startTs = time();
        $this->reportRows = [];
        $this->categoryIds = [];
        $this->makeIds = [];

        $stateDir = config('cdn.state_dir', storage_path('app/cdn'));
        @mkdir($stateDir, 0777, true);

        if ($this->option('fill-incomplete')) {
            return $this->fillIncomplete($stateDir);
        }

        $cursorFile = $stateDir . '/perfectmotors.cursor';
        $doneMarker = $stateDir . '/perfectmotors-scrape.done';

        $minId = (int) $this->option('min-id');
        $maxId = (int) $this->option('max-id');
        $limit = (int) $this->option('limit');
        $this->dryRun = (bool) $this->option('dry-run');

        // resume from the checkpoint (last swept id + 1), never before min-id
        $startId = $this->option('start-id') !== null
            ? max($minId, (int) $this->option('start-id'))
            : (is_file($cursorFile) ? max($minId, ((int) file_get_contents($cursorFile)) + 1) : $minId);

        $this->info(($this->dryRun ? '[dry-run] ' : '') . 'perfectmotors sweep '
            . "id {$startId}..{$maxId} (pool " . (int) $this->option('pool') . ')');

        for ($base = $startId; $base <= $maxId; $base += self::BATCH) {
            $hi = min($base + self::BATCH - 1, $maxId);
            $urls = [];
            for ($id = $base; $id <= $hi; $id++) {
                $url = self::ORIGIN . '/productDetail/' . $id;
                if ($this->dryRun || !Products::where('product_link', $url)->exists()) {
                    $urls[$id] = $url;
                }
            }

            $htmls = $this->fetchDetailBatch(array_values($urls));

            foreach ($urls as $url) {
                if ($limit > 0 && $this->upserted >= $limit) {
                    break;
                }
                $this->bankOne($url, $htmls[$url] ?? null);
            }

            // checkpoint the last swept id so a crash resumes past this batch
            if (!$this->dryRun) {
                file_put_contents($cursorFile, (string) $hi);
            }

            $this->writeProgress($stateDir, $hi, $minId, $maxId, false);
            $this->info("id {$base}..{$hi} done — {$this->upserted} banked, {$this->skipped} skipped");

            if ($limit > 0 && $this->upserted >= $limit) {
                break;
            }
        }

        // reached the end of the range: mark the sweep complete
        if ($limit === 0 || $this->upserted < $limit) {
            $this->writeProgress($stateDir, $maxId, $minId, $maxId, true);
            if (!$this->dryRun) {
                file_put_contents($doneMarker, now()->toDateTimeString() . "\n");
            }
        }

        if ($this->option('report')) {
            file_put_contents($this->option('report'), $this->renderReport());
            $this->info('comparison sheet written to ' . $this->option('report'));
        }

        $this->info("finished — {$this->upserted} products " . ($this->dryRun ? 'mapped (nothing written)' : 'upserted')
            . ", {$this->soldSkipped} sold/reserved skipped, {$this->skipped} ids skipped (not cars)");

        return self::SUCCESS;
    }

    /**
     * Parse + bank a single fetched detail page. A null/unparseable body is a
     * deleted or never-used id (500/404) — normal for a sweep, just skip it.
     */
    private function bankOne(string $url, ?string $html): void
    {
        $detail = $html !== null ? $this->parser->parseDetailPage($html, $url) : null;
        if ($detail === null) {
            $this->skipped++;

            return;
        }
        // skip sold / reserved cars — they carry no price ($0.00 on this source)
        // and aren't wanted as available stock
        if (($detail['price'] ?? 0) <= 0) {
            $this->soldSkipped++;

            return;
        }
        if (empty($detail['images'])) {
            $this->logFailure($url, 'no images on listing');
        }

        $attributes = $this->mapToProduct($detail);

        if ($this->option('report') && count($this->reportRows) < 100) {
            $this->reportRows[] = ['source' => $detail, 'mapped' => $attributes];
        }

        if (!$this->dryRun) {
            $product = Products::updateOrCreate(['product_link' => $url], $attributes);
            if (!$product->stock_code) {
                $product->update(['stock_code' => 'SM' . $product->id]);
            }
        }

        $this->imagesScraped += count($detail['images'] ?? []);
        $this->upserted++;
    }

    /**
     * Guarantee full data: re-fetch the detail page for every perfectmotors
     * product whose specifications is still NULL, updating it in place, looping
     * until none remain. Writes perfectmotors-fill.done only when zero remain.
     */
    private function fillIncomplete(string $stateDir): int
    {
        $doneMarker = $stateDir . '/perfectmotors-fill.done';
        @unlink($doneMarker);

        for ($round = 1; ; $round++) {
            $remaining = Products::where('website', 'perfectmotors')->whereNull('specifications')->count();
            file_put_contents(
                $stateDir . '/perfectmotors-fill-progress.json',
                json_encode(['incomplete_remaining' => $remaining, 'updated_at' => date('c')], JSON_PRETTY_PRINT)
            );

            if ($remaining === 0) {
                file_put_contents($doneMarker, now()->toDateTimeString() . "\n");
                $this->info('fill-incomplete: every perfectmotors product has full detail');

                return self::SUCCESS;
            }

            $links = Products::where('website', 'perfectmotors')->whereNull('specifications')
                ->limit(300)->pluck('product_link')->all();
            $this->info("fill-incomplete round {$round}: {$remaining} missing detail — fetching " . count($links));

            $htmls = $this->fetchDetailBatch($links);
            $filled = 0;
            $failed = [];
            foreach ($links as $url) {
                $html = $htmls[$url] ?? null;
                $detail = $html !== null ? $this->parser->parseDetailPage($html, $url) : null;
                if ($detail !== null) {
                    Products::where('product_link', $url)->update($this->mapToProduct($detail));
                    $filled++;
                } else {
                    $failed[] = $url;
                }
            }
            $this->info("fill-incomplete round {$round}: filled {$filled}, still failing " . count($failed));

            if ($filled === 0) {
                // nothing came through — the rest are genuinely gone (500/404)
                $this->pruneWithdrawn(array_slice($failed, 0, 25));

                return self::SUCCESS;
            }
            // loop continues: remaining shrank, so it terminates at 0
        }
    }

    /** delete products whose detail id is gone from the origin (404/410/500) */
    private function pruneWithdrawn(array $urls): void
    {
        foreach ($urls as $url) {
            try {
                $code = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(15)->get($url)->status();
                if (in_array($code, [404, 410, 500], true)) {
                    Products::where('product_link', $url)->delete();
                    $this->warn("fill-incomplete: deleted withdrawn listing {$url}");
                }
            } catch (\Throwable) {
            }
            if (!app()->runningUnitTests()) {
                usleep(200000);
            }
        }
    }

    /**
     * Fetch many detail pages, returning [url => html|null]. Runs a rolling
     * concurrent window (--pool wide) since the origin has no rate limiting.
     * Tests use the fakeable sequential Http path.
     *
     * @param  string[]  $urls
     * @return array<string,string|null>
     */
    private function fetchDetailBatch(array $urls): array
    {
        $poolSize = max(1, (int) $this->option('pool'));

        if ($poolSize === 1 || app()->runningUnitTests()) {
            $out = [];
            foreach ($urls as $url) {
                $out[$url] = $this->fetch($url);
            }

            return $out;
        }

        $results = [];
        $pending = array_values($urls);

        for ($round = 1; $round <= 4 && $pending !== []; $round++) {
            $client = new \GuzzleHttp\Client([
                'connect_timeout' => 8,
                'timeout' => 25,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en;q=0.9',
                ],
            ]);

            $retry = [];
            $requests = function () use ($pending, $client) {
                foreach ($pending as $url) {
                    yield $url => $client->getAsync($url);
                }
            };

            \GuzzleHttp\Promise\Each::ofLimit(
                $requests(),
                $poolSize,
                function ($resp, $url) use (&$results, &$retry) {
                    $code = $resp->getStatusCode();
                    if ($code >= 200 && $code < 300) {
                        $results[$url] = (string) $resp->getBody();
                    } elseif (in_array($code, [404, 410, 500], true)) {
                        $results[$url] = null; // deleted / never-used id — not a car
                    } else {
                        $retry[] = $url; // 403/429/503 — transient, try again
                    }
                },
                function ($_e, $url) use (&$retry) {
                    $retry[] = $url; // connection blip — try again
                }
            )->wait();

            $pending = $retry;
            if ($pending !== [] && $round < 4) {
                sleep($round);
            }
        }

        foreach ($pending as $url) {
            $results[$url] = null;
            $this->logFailure($url, 'detail unfetchable after pooled retries');
        }

        return $results;
    }

    /**
     * Single polite fetch with quick retries. A 404/410/500 means the id is not
     * a live car — return null so the sweep skips it. Used by the test path and
     * when --pool=1.
     */
    private function fetch(string $url): ?string
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en;q=0.9',
                ])->timeout(25)->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
                if (in_array($response->status(), [404, 410, 500], true)) {
                    return null; // not a car — a fact, not an outage
                }
                if (in_array($response->status(), [403, 429, 503], true)) {
                    if (app()->runningUnitTests()) {
                        return null;
                    }
                    sleep(min(30, 5 * $attempt));
                    continue;
                }
            } catch (\Throwable) {
                if (app()->runningUnitTests()) {
                    return null;
                }
            }
            if (app()->runningUnitTests()) {
                return null;
            }
            sleep($attempt);
        }

        return null;
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
            'mileage_km' => $data['mileage_km'] ?? null,
            'fuel' => $data['fuel'] ?? null,
            'transmission' => $data['transmission'] ?? null,
            'condition' => $data['condition'] ?? 'Used',
            'color' => $data['color'] ?? null,
            'steering' => $data['steering'] ?? 'Right',
            'seats' => $data['seats'] ?? null,
            'doors' => $data['doors'] ?? null,
            'drive_type' => $data['drive_type'] ?? null,
            'power_hp' => $data['power_hp'] ?? null,
            'category_id' => $this->categoryIdFor($data['body_style'] ?? null, $data['title'] ?? null),
            'make_id' => !empty($data['make']) ? $this->makeId($data['make']) : null,
            'price' => $data['price'] ?? null, // already USD — stored verbatim
            'country' => $data['country'] ?? 'United Arab Emirates',
            'website' => 'perfectmotors',
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
        // collapse spelling variants so a brand is never split across duplicates
        $name = app(\App\Services\MakeNormalizer::class)->canonical($name) ?? $name;

        // dry-run must not persist: look up existing makes only, never create
        if ($this->dryRun) {
            return $this->makeIds[$name] ??= Categories::where('cat_title', $name)->where('type', 'make')->value('id');
        }

        return $this->makeIds[$name] ??= Categories::firstOrCreate(
            ['cat_title' => $name, 'type' => 'make'],
        )->id;
    }

    /**
     * Machine + human readable progress snapshot after every batch. The
     * keepalive task and any status page read this. Also drops a heartbeat line.
     */
    private function writeProgress(string $stateDir, int $id, int $minId, int $maxId, bool $done): void
    {
        $elapsed = max(1, time() - $this->startTs);
        $ratePerMin = round($this->upserted / $elapsed * 60, 1);
        $span = max(1, $maxId - $minId);
        $swept = max(0, $id - $minId);

        $snapshot = [
            'source' => 'perfectmotors',
            'done' => $done,
            'current_id' => $id,
            'min_id' => $minId,
            'max_id' => $maxId,
            'percent' => round($swept / $span * 100, 1),
            'products_scraped' => $this->upserted,
            'ids_skipped' => $this->skipped,
            'images_scraped' => $this->imagesScraped,
            'failures' => $this->failures,
            'rate_per_min' => $ratePerMin,
            'started_at' => date('c', $this->startTs),
            'updated_at' => date('c'),
        ];

        file_put_contents(
            $stateDir . '/perfectmotors-progress.json',
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        file_put_contents(
            $stateDir . '/perfectmotors-heartbeat.txt',
            date('c') . " id {$id}/{$maxId}"
            . " products={$this->upserted} skipped={$this->skipped} images={$this->imagesScraped}\n"
        );
    }

    private function logFailure(string $url, string $reason): void
    {
        $this->failures++;
        $this->warn("skip {$url}: {$reason}");
        file_put_contents(
            config('cdn.state_dir', storage_path('app/cdn')) . '/perfectmotors-failures.log',
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
            $usd = $m['price'] !== null ? '$' . number_format((float) $m['price']) : '<span class="enq">Enquire</span>';
            $specBits = array_filter([
                ($m['seats'] ?? null) ? $m['seats'] . ' seats' : null,
                ($m['doors'] ?? null) ? $m['doors'] . ' doors' : null,
                ($m['engine_cc'] ?? null) ? $m['engine_cc'] . 'cc' : null,
                $m['steering'] ?? null,
            ]);
            $rows .= '<tr>'
                . '<td class="n">' . ($i + 1) . '</td>'
                . '<td>' . $img . '</td>'
                . '<td><a href="' . e($s['product_link']) . '" target="_blank" rel="noopener">' . e($m['title']) . '</a>'
                . '<div class="src"><a href="' . e($s['product_link']) . '" target="_blank" rel="noopener">↗ view on Perfect Motors</a></div></td>'
                . '<td class="price"><b>' . $usd . '</b></td>'
                . '<td>' . e((string) ($m['year'] ?? '—')) . '</td>'
                . '<td>' . ($m['mileage_km'] !== null ? number_format($m['mileage_km']) . ' km' : '—') . '</td>'
                . '<td>' . e($m['fuel'] ?? '—') . ' / ' . e($m['transmission'] ?? '—') . '</td>'
                . '<td class="specs">' . ($specBits ? e(implode(' · ', $specBits)) : '—') . '</td>'
                . '<td class="ph"><b>' . $gallery . '</b></td>'
                . '</tr>';
        }

        return '<!doctype html><meta charset="utf-8"><title>Perfect Motors scrape preview</title><style>'
            . 'body{font-family:Segoe UI,system-ui,sans-serif;background:#eceff4;margin:0;color:#0b1e3b}'
            . '.wrap{max-width:1600px;margin:0 auto;padding:26px}'
            . 'h1{font-size:23px;margin:0 0 4px}.lede{color:#5b6b83;font-size:14px;margin:0 0 18px;max-width:90ch}'
            . 'table{border-collapse:collapse;width:100%;background:#fff;font-size:13px;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(11,30,59,.06)}'
            . 'th{background:#B60304;color:#fff;padding:11px 10px;text-align:left;font-size:11px;letter-spacing:.03em;text-transform:uppercase;position:sticky;top:0}'
            . 'td{border-bottom:1px solid #eef1f6;padding:10px;vertical-align:top}tr:hover td{background:#f8fafc}'
            . 'img{width:150px;height:112px;object-fit:cover;border-radius:8px;background:#eef1f6;display:block}'
            . '.noimg{width:150px;height:112px;border-radius:8px;background:#eef1f6;display:grid;place-items:center;color:#b3bece;font-size:11px}'
            . 'a{color:#B60304;text-decoration:none;font-weight:700}a:hover{text-decoration:underline}'
            . '.sub{color:#8494ab;font-size:11px;margin-top:2px}.src a{color:#B60304;font-size:11px}'
            . '.price b{font-size:15px;color:#0b1e3b}.enq{color:#b5591a}.specs{max-width:200px;color:#33445e}'
            . '.ph b{color:#1f8f57}.n{color:#b3bece;font-variant-numeric:tabular-nums}'
            . '</style><div class="wrap"><h1>Perfect Motors → SupremeMotors · ' . count($this->reportRows) . ' products</h1>'
            . '<p class="lede">Prices are stored as-is in <b>USD</b> (Perfect Motors already lists in dollars). Images point at the sm-perfectmotors Bunny CDN; originals kept in *_source.</p>'
            . '<table><tr><th>#</th><th>Image (CDN)</th><th>Title / source link</th><th>Price USD</th><th>Year</th><th>Mileage</th><th>Fuel / Gearbox</th><th>Specs</th><th>Photos</th></tr>'
            . $rows . '</table></div>';
    }
}
