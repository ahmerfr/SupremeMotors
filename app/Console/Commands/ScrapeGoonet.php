<?php

namespace App\Console\Commands;

use App\Models\Products;
use App\Services\GoonetParser;
use App\Services\SchannelCurl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scrape goo-net-exchange.com — Japanese used-car export inventory.
 *
 * Enumerate 175 brand_cd values off the homepage make-select; each brand's export
 * stock is paged via summary.php?brand_cd=<CD>&offset=<20*(page-1)> (20 cars/page).
 * Full data (fuel/drive/doors/body/gallery) lives on the detail page, so --run reads
 * each detail page. Insert-only + dedup-safe + live-safe chunk export, like the other
 * sources. The site rate-limits, so fetches warm a session cookie first and retry with
 * backoff on 429.
 */
class ScrapeGoonet extends Command
{
    protected $signature = 'scrape:goonet
        {--enumerate : list brand_cd + export totals off the homepage into storage}
        {--build-blocklist : sample cars, hash gallery images, and write a promo-image blocklist (content recurring across many distinct cars)}
        {--sample=150 : cars to sample for --build-blocklist}
        {--min-cars=6 : blocklist image content that appears across >= this many DISTINCT cars (promos/sample banners)}
        {--preview=0 : fetch N cars (listing+detail) and render public/goonet-preview.html — NO DB writes}
        {--run : crawl all brands and INSERT new goonet rows into the LOCAL db}
        {--export-chunk : dump website=goonet rows to db-export/goonet-*.zip for live import}
        {--brand= : restrict --preview/--run to one brand_cd (default: all; preview default Toyota 1010)}
        {--shards=1 : split the brand work-list into this many shards (one per GitHub runner)}
        {--shard=1 : which shard (1..shards) this process handles}
        {--jsonl-out= : with --run, write normalised rows as JSON-lines to this file instead of inserting to the DB (for GitHub-runner crawls with no DB)}
        {--strip : with --run, hash each gallery and drop promo/ad images (seed+auto blocklist), keeping the cover — feasible on GitHub runners across 20 IPs}
        {--import-jsonl= : load crawl JSONL file(s) (a dir or one file) into the DB, insert-only + dedup-safe}
        {--pool=6 : concurrent detail fetches (the site rate-limits above ~8)}
        {--gallery-limit=40 : store front + up to this many gallery images}
        {--jpy-usd=0.00622 : JPY->USD rate (goo-net implies ~0.00622 = 160.6 JPY/USD)}
        {--max-pages=3000 : safety cap on listing pages per brand}';

    protected $description = 'Scrape goo-net-exchange.com stock (listing+detail, insert-only, live-safe chunk export)';

    private const SITE = 'https://www.goo-net-exchange.com';

    private const SUMMARY = 'https://www.goo-net-exchange.com/php/search/summary.php';

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

    private SchannelCurl $curl;

    private GoonetParser $parser;

    private string $jar;

    private int $inserted = 0;

    public function handle(): int
    {
        $this->curl = new SchannelCurl(null, sys_get_temp_dir() . '/goonet');
        $this->jar = storage_path('app/cdn/goonet.jar');
        @mkdir(dirname($this->jar), 0777, true);
        $this->parser = new GoonetParser((float) $this->option('jpy-usd'));

        // warm a session cookie (the site rate-limits cold clients harder)
        $this->get(self::SITE . '/');

        if ($this->option('enumerate')) {
            return $this->enumerate();
        }
        if ($this->option('build-blocklist')) {
            return $this->buildBlocklist();
        }
        if ((int) $this->option('preview') > 0) {
            return $this->preview((int) $this->option('preview'));
        }
        if ($this->option('run')) {
            return $this->runInsert();
        }
        if ($this->option('import-jsonl')) {
            return $this->importJsonl();
        }
        if ($this->option('export-chunk')) {
            return $this->exportChunk();
        }
        $this->error('choose one: --enumerate | --preview=N | --run | --export-chunk');

        return self::FAILURE;
    }

    /* -------------------------------------------------------------- transport */

    private function headers(): array
    {
        return ['User-Agent' => self::UA, 'Accept' => 'text/html', 'Accept-Language' => 'en'];
    }

    /** GET with backoff retries on non-200 (the site 429s under load). */
    private function get(string $url, int $tries = 4): string
    {
        for ($i = 0; $i < $tries; $i++) {
            [$status, $html] = $this->curl->request('GET', $url, $this->headers(), null, $this->jar, 40);
            if ($status === 200 && $html !== '') {
                return $html;
            }
            usleep(1200000 * ($i + 1));   // 1.2s, 2.4s, 3.6s
        }

        return '';
    }

    /**
     * Fetch many detail URLs concurrently, retrying the 429/failed ones a couple
     * more times at reduced pressure.
     *
     * @param  list<string>  $urls
     * @return array<string,string>  url => html (only successful ones)
     */
    private function getMany(array $urls, int $pool): array
    {
        $out = [];
        $pending = $urls;
        for ($pass = 1; $pass <= 3 && $pending !== []; $pass++) {
            $req = [];
            foreach ($pending as $u) {
                $req[$u] = ['method' => 'GET', 'url' => $u, 'headers' => $this->headers()];
            }
            $res = $this->curl->parallel($req, $this->jar, $pool, 40);
            $next = [];
            foreach ($pending as $u) {
                [$status, $html] = $res[$u] ?? [0, ''];
                if ($status === 200 && $html !== '') {
                    $out[$u] = $html;
                } else {
                    $next[] = $u;
                }
            }
            $pending = $next;
            if ($pending !== []) {
                usleep(2500000);   // cool-off before retrying the throttled ones
            }
        }

        return $out;
    }

    /* -------------------------------------------------------------- enumerate */

    private function enumerate(): int
    {
        $home = $this->get(self::SITE . '/');
        $brands = $this->parser->parseBrands($home);
        $this->info(count($brands) . ' brand_cd values found; probing export totals...');

        $file = storage_path('app/cdn/goonet-brands.txt');
        $fh = fopen($file, 'w');
        $grand = 0;
        foreach ($brands as $cd => $b) {
            $html = $this->get(self::SUMMARY . '?brand_cd=' . $cd . '&offset=0');
            $total = $this->parser->parseTotal($html) ?? 0;
            if ($total > 0) {
                fwrite($fh, $cd . "\t" . $b['name'] . "\t" . $total . "\n");
                $grand += $total;
            }
            usleep(300000);
        }
        fclose($fh);
        $this->info("ENUMERATE DONE: export total {$grand} across brands with stock -> {$file}");

        return self::SUCCESS;
    }

    /* -------------------------------------------------------------- blocklist */

    private function imageHeaders(): array
    {
        return ['User-Agent' => self::UA, 'Referer' => self::SITE . '/', 'Accept' => 'image/avif,image/webp,image/*,*/*'];
    }

    /**
     * Download images concurrently and return url => ['md5'=>, 'size'=>] for the
     * ones that came back as real image bytes (>1KB).
     *
     * @param  list<string>  $urls
     * @return array<string,array{md5:string,size:int}>
     */
    private function downloadHashes(array $urls, int $pool): array
    {
        $out = [];
        foreach (array_chunk($urls, max(1, $pool) * 8) as $batch) {
            $req = [];
            foreach ($batch as $u) {
                $req[$u] = ['method' => 'GET', 'url' => $u, 'headers' => $this->imageHeaders()];
            }
            $res = $this->curl->parallel($req, $this->jar, $pool, 30);
            foreach ($batch as $u) {
                [$status, $body] = $res[$u] ?? [0, ''];
                if ($status === 200 && strlen($body) > 1024) {
                    $out[$u] = ['md5' => md5($body), 'size' => strlen($body)];
                }
            }
            usleep(300000);
        }

        return $out;
    }

    /**
     * Build the promo-image blocklist. Dealers append generic banners (FLEX
     * warranty ad, "sample image" inspection shots) to their real car photos;
     * these are byte-identical across thousands of listings while a real car
     * photo is unique to one car. So we sample cars from several brands, hash
     * every gallery image, and blocklist any content that shows up across
     * >= --min-cars DISTINCT cars. A real photo can never hit that threshold.
     */
    private function buildBlocklist(): int
    {
        $sample = max(30, (int) $this->option('sample'));
        $minCars = max(2, (int) $this->option('min-cars'));
        $pool = max(4, min((int) $this->option('pool'), 8));

        // spread the sample across brands for dealer diversity
        $brands = array_keys($this->parser->parseBrands($this->get(self::SITE . '/')));
        if ($this->option('brand')) {
            $brands = [$this->option('brand')];
        }
        $spread = min(count($brands), 30);   // more brands = more dealer diversity
        $perBrand = max(20, (int) ceil($sample / max(1, $spread)));

        $detailUrls = [];
        foreach (array_slice($brands, 0, $spread) as $cd) {
            for ($page = 0; count($detailUrls) < $sample; $page++) {
                $got = $this->parser->parseListingUrls($this->get(self::SUMMARY . '?brand_cd=' . $cd . '&offset=' . ($page * 20)));
                if ($got === [] || $page > ($perBrand / 20)) {
                    break;
                }
                $detailUrls = array_merge($detailUrls, $got);
                usleep(500000);
            }
            if (count($detailUrls) >= $sample) {
                break;
            }
        }
        $detailUrls = array_slice(array_values(array_unique($detailUrls)), 0, $sample);
        $this->info(count($detailUrls) . ' cars sampled; fetching details + hashing images...');

        // gather each car's gallery image URLs
        $carImages = [];
        foreach (array_chunk($detailUrls, 40) as $chunk) {
            $htmls = $this->getMany($chunk, $pool);
            foreach ($chunk as $u) {
                if (!isset($htmls[$u])) {
                    continue;
                }
                $row = $this->parser->parseDetail($htmls[$u], $u);
                if ($row !== null && count($row['images']) > 1) {
                    // Hash the WHOLE gallery (cover excluded) — promos sit at BOTH ends:
                    // dealer ad banners at the START (e.g. FLEX "0円" plan = G00101) and
                    // "サンプル画像" inspection shots at the tail. A tail-only scan misses
                    // the leading ads. Cover (images[0]) is excluded so a reused
                    // placeholder cover can never enter the blocklist.
                    $carImages[$u] = array_slice($row['images'], 1);
                }
            }
        }

        // hash every image, tracking DISTINCT cars per content hash
        $hashCars = [];   // md5 => [carUrl => true]
        $hashMeta = [];   // md5 => ['size'=>, 'sample'=>]
        $done = 0;
        foreach ($carImages as $carUrl => $imgs) {
            $hashes = $this->downloadHashes($imgs, $pool);
            foreach ($hashes as $imgUrl => $h) {
                $hashCars[$h['md5']][$carUrl] = true;
                $hashMeta[$h['md5']] ??= ['size' => $h['size'], 'sample' => $imgUrl];
            }
            if (++$done % 20 === 0) {
                $this->info("  hashed {$done}/" . count($carImages) . ' cars, ' . count($hashCars) . ' distinct images');
            }
        }

        // blocklist = content appearing across >= minCars distinct cars
        $blocklist = [];
        foreach ($hashCars as $md5 => $cars) {
            $n = count($cars);
            if ($n >= $minCars) {
                $blocklist[$md5] = ['cars' => $n, 'size' => $hashMeta[$md5]['size'], 'sample_url' => $hashMeta[$md5]['sample']];
            }
        }
        arsort($blocklist);   // most-repeated first (kept via cars count) — note: arsort on array uses value compare; keep simple

        $file = storage_path('app/cdn/goonet-image-blocklist.json');
        file_put_contents($file, json_encode([
            'built_from_cars' => count($carImages),
            'min_cars' => $minCars,
            'blocked' => $blocklist,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('BLOCKLIST DONE: ' . count($blocklist) . ' promo images (>= ' . $minCars . ' cars) from ' . count($carImages) . ' sampled cars -> ' . $file);
        foreach (array_slice($blocklist, 0, 12, true) as $md5 => $b) {
            $this->line(sprintf('  %d cars  %6dB  %s', $b['cars'], $b['size'], $b['sample_url']));
        }

        return self::SUCCESS;
    }

    /**
     * Known promo/ad image md5s, verified by eye (a council downloaded + viewed
     * each). Seeded so they are stripped regardless of the >=min-cars recurrence
     * count — dealers rotate front ads, so a single ad may not hit 6 on a small
     * crawl. These are generic banners reused across listings, never a real car.
     */
    private const SEED_PROMOS = [
        '46dab262477cf5e0645ef25f82bcb3bc',  // FLEX "ドライブ安心プラン 0円" warranty ad
        'a50345434af25ae6cb9a980c199e53ad',  // FLEX "コンプリートカー" front ad
        'a86940df53ddab3406398c45e88024d3',  // FINE TRUST "OBD診断 & 第三者機関の鑑定" trust banner
        '995cd288ad5ba5fa187e4cb65de99504',  // サンプル画像 inspection banners (goo鑑定 stock shots) ...
        '9fcfdc18c89084ac7dbe8359e7cccb01',
        '4043f09c01b146906c41f0167f0f9197',
        '2aa832e9865e175e621b595a1deb3208',
        '231936efd0da793c72bf3e832ff22fe6',
        'fefb358f1ff2f81c942dd0d478687cf5',
        '9969e06218d109322a1e40142aa458fb',
        '4a38cc3359f5a7d95b0cace071ae6c28',
        '2ba1f6d2655849e01eafea44ce7f1bb0',
        'ebfc6f5fcda67f9a05b309b1be73c877',
    ];

    /** md5 set of blocklisted promo images = verified seed UNION the auto-built list */
    private function loadBlocklist(): array
    {
        $set = array_fill_keys(self::SEED_PROMOS, true);
        $file = storage_path('app/cdn/goonet-image-blocklist.json');
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            foreach (array_keys($data['blocked'] ?? []) as $md5) {
                $set[$md5] = true;
            }
        }

        return $set;
    }

    /**
     * Drop gallery images whose content is in the promo blocklist. The cover
     * (images[0]) is ALWAYS kept and never re-tested, and if every gallery image
     * turns out to be a promo the car still keeps its cover — so no car is ever
     * left image-less.
     *
     * @param  list<string>  $images
     * @param  array<string,bool>  $blockset  md5 => true
     * @return list<string>
     */
    private function stripPromos(array $images, array $blockset, int $pool, int &$stripped): array
    {
        if (count($images) < 2) {
            return $images;
        }
        $cover = $images[0];
        $gallery = array_slice($images, 1);
        $hashes = $this->downloadHashes($gallery, $pool);
        $clean = [];
        foreach ($gallery as $g) {
            $md5 = $hashes[$g]['md5'] ?? null;
            if ($md5 !== null && isset($blockset[$md5])) {
                $stripped++;

                continue;
            }
            $clean[] = $g;
        }

        return array_merge([$cover], $clean);
    }

    /* ---------------------------------------------------------------- preview */

    private function preview(int $n): int
    {
        $brand = $this->option('brand') ?: '1010';   // Toyota by default
        $pool = max(2, min((int) $this->option('pool'), 8));
        $limit = (int) $this->option('gallery-limit');

        // collect detail URLs across listing pages until we have N
        $urls = [];
        for ($page = 0; count($urls) < $n && $page < 5; $page++) {
            $html = $this->get(self::SUMMARY . '?brand_cd=' . $brand . '&offset=' . ($page * 20));
            $pageUrls = $this->parser->parseListingUrls($html);
            if ($pageUrls === []) {
                break;
            }
            $urls = array_merge($urls, $pageUrls);
            usleep(800000);
        }
        $urls = array_slice(array_values(array_unique($urls)), 0, $n);
        $this->info(count($urls) . " detail URLs collected (brand {$brand}); fetching...");

        $blockset = $this->loadBlocklist();
        $htmls = $this->getMany($urls, $pool);
        $rows = [];
        foreach ($urls as $u) {
            if (!isset($htmls[$u])) {
                continue;
            }
            $row = $this->parser->parseDetail($htmls[$u], $u);
            if ($row === null) {
                continue;
            }
            $row['images'] = array_slice($row['images'], 0, $limit + 1);
            $row['stripped'] = 0;
            if ($blockset !== []) {
                $row['images'] = $this->stripPromos($row['images'], $blockset, $pool, $row['stripped']);
                $row['front_image'] = $row['images'][0] ?? $row['front_image'];
            }
            $rows[] = $row;
        }

        $this->renderPreview($rows);
        $this->info(count($rows) . ' cars parsed. Preview -> public/goonet-preview.html (NO DB writes).');
        // console summary
        foreach ($rows as $r) {
            $this->line(sprintf(
                '  %-22s %s  $%s  %s/%s/%s  %s imgs',
                mb_substr($r['make'] . ' ' . $r['model'], 0, 22),
                $r['year'] ?? '----',
                number_format($r['price_usd']),
                $r['fuel'] ?? '-',
                $r['transmission'] ?? '-',
                $r['drive_type'] ?? '-',
                count($r['images'])
            ));
        }

        return self::SUCCESS;
    }

    private function renderPreview(array $rows): void
    {
        $cards = '';
        foreach ($rows as $r) {
            $img = $r['front_image'] ?? '';
            $specs = array_filter([
                $r['year'], $r['mileage_km'] ? number_format($r['mileage_km']) . ' km' : null,
                $r['fuel'], $r['transmission'], $r['drive_type'],
                $r['engine_cc'] ? $r['engine_cc'] . 'cc' : null, $r['color'],
                $r['steering'] ? $r['steering'] . '-hand' : null,
            ]);
            $cards .= '<div class="c"><img loading="lazy" src="' . htmlspecialchars($img) . '"><div class="b">'
                . '<h3>' . htmlspecialchars($r['title']) . '</h3>'
                . '<p class="pr">$' . number_format($r['price_usd']) . ' <span>(¥' . number_format((int) $r['price_jpy']) . ')</span></p>'
                . '<p class="sp">' . htmlspecialchars(implode(' · ', $specs)) . '</p>'
                . '<p class="im">' . count($r['images']) . ' photos · <a href="' . htmlspecialchars($r['product_link']) . '" target="_blank">source</a></p>'
                . '</div></div>';
        }
        $html = '<!doctype html><meta charset="utf-8"><title>goo-net preview</title><style>'
            . 'body{font:14px system-ui;margin:0;background:#0d0d0f;color:#eee;padding:24px}'
            . 'h1{font-weight:800}.g{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}'
            . '.c{background:#17171b;border:1px solid #262630;border-radius:14px;overflow:hidden}'
            . '.c img{width:100%;height:180px;object-fit:cover;background:#222}.b{padding:12px}'
            . 'h3{margin:0 0 6px;font-size:15px}.pr{color:#8e2527;font-weight:700;margin:0 0 6px}.pr span{color:#888;font-weight:400;font-size:12px}'
            . '.sp{color:#bbb;margin:0 0 6px;font-size:12.5px}.im{color:#777;margin:0;font-size:12px}.im a{color:#6b9}'
            . '</style><h1>goo-net-exchange — ' . count($rows) . ' sample cars (listing+detail, no DB)</h1><div class="g">'
            . $cards . '</div>';
        file_put_contents(public_path('goonet-preview.html'), $html);
    }

    /* -------------------------------------------------------------------- run */

    private function runInsert(): int
    {
        $pool = max(2, min((int) $this->option('pool'), 8));
        $limit = (int) $this->option('gallery-limit');
        $maxPages = (int) $this->option('max-pages');
        $strip = (bool) $this->option('strip');
        $blockset = $strip ? $this->loadBlocklist() : [];

        // brand work-list: from --enumerate file if present, else the homepage
        $brands = [];
        $file = storage_path('app/cdn/goonet-brands.txt');
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $ln) {
                [$cd] = explode("\t", $ln);
                $brands[] = $cd;
            }
        } else {
            $brands = array_keys($this->parser->parseBrands($this->get(self::SITE . '/')));
        }
        if ($this->option('brand')) {
            $brands = [$this->option('brand')];
        }

        // Shard the brand work-list across N runners (GitHub matrix): runner K
        // takes every Nth brand. Deterministic, gap-free, no coordination needed.
        $shards = max(1, (int) $this->option('shards'));
        $shard = max(1, min((int) $this->option('shard'), $shards));
        if ($shards > 1) {
            $brands = array_values(array_filter($brands, fn ($cd, $i) => $i % $shards === $shard - 1, ARRAY_FILTER_USE_BOTH));
        }

        // JSONL mode = write rows to a file, no DB (for GitHub-runner crawls). Galleries
        // are emitted RAW; the promo blocklist is applied later (import/warm), where every
        // image is hashed once — hashing 10M images inline would blow the 6h runner cap.
        $jsonlOut = (string) $this->option('jsonl-out');
        $jh = null;
        $existing = collect();
        if ($jsonlOut !== '') {
            $jh = fopen($jsonlOut, 'w');
        } else {
            $existing = DB::table('products')->where('website', 'goonet')->pluck('product_link')->flip();
        }
        $this->info(count($existing) . " goonet rows present. Shard {$shard}/{$shards}, brands: " . count($brands) . ($jh ? " -> JSONL {$jsonlOut}" : ''));

        foreach ($brands as $cd) {
            $empty = 0;
            for ($page = 0; $page < $maxPages; $page++) {
                $html = $this->get(self::SUMMARY . '?brand_cd=' . $cd . '&offset=' . ($page * 20));
                $urls = $this->parser->parseListingUrls($html);
                if ($urls === []) {
                    if (++$empty >= 2) {
                        break;   // two empties = end of this brand
                    }

                    continue;
                }
                $empty = 0;
                $fresh = array_values(array_filter($urls, fn ($u) => !isset($existing[$u])));
                if ($fresh === []) {
                    continue;
                }
                $htmls = $this->getMany($fresh, $pool);
                foreach ($fresh as $u) {
                    if (!isset($htmls[$u])) {
                        continue;
                    }
                    $row = $this->parser->parseDetail($htmls[$u], $u);
                    if ($row === null) {
                        continue;
                    }
                    $row['images'] = array_slice($row['images'], 0, $limit + 1);
                    if ($strip) {
                        $s = 0;
                        $row['images'] = $this->stripPromos($row['images'], $blockset, $pool, $s);
                        $row['front_image'] = $row['images'][0] ?? $row['front_image'];
                    }
                    if ($jh !== null) {
                        fwrite($jh, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                        $this->inserted++;
                    } else {
                        $this->insertRow($row);
                        $existing[$u] = true;
                    }
                }
                if ($page % 10 === 0) {
                    $this->info("  brand {$cd} page {$page}: inserted total {$this->inserted}");
                }
            }
        }
        if ($jh !== null) {
            fclose($jh);
        }
        $this->info("RUN DONE: inserted {$this->inserted} goonet cars.");

        return self::SUCCESS;
    }

    private function insertRow(array $r): void
    {
        try {
            $p = Products::create([
                'title' => $r['title'],
                'model' => $r['model'] !== '' ? mb_substr($r['model'], 0, 255) : null,
                'year' => $r['year'],
                'mileage_km' => $r['mileage_km'],
                'fuel' => $r['fuel'],
                'transmission' => $r['transmission'],
                'condition' => $r['condition'],
                'color' => $r['color'],
                'body_style' => $r['body_style'],
                'engine_cc' => $r['engine_cc'],
                'drive_type' => $r['drive_type'],
                'doors' => $r['doors'],
                'steering' => $r['steering'],
                'category_id' => $r['category_id'],
                'price' => (float) $r['price_usd'],
                'website' => 'goonet',
                'country' => $r['country'],
                'product_link' => $r['product_link'],
                'front_image' => $r['front_image'],
                'front_image_source' => $r['front_image'],
                'other_images' => $r['images'],
                'other_images_source' => json_encode($r['images'], JSON_UNESCAPED_SLASHES),
                'product_details' => $r['product_details'],
            ]);
            $p->update(['stock_code' => 'GN' . $p->id]);
            $this->inserted++;
        } catch (\Throwable $e) {
            $this->warn('insert failed ' . $r['product_link'] . ': ' . $e->getMessage());
        }
    }

    /**
     * Load crawl JSONL (one normalised row per line, as written by --run --jsonl-out
     * on the GitHub runners) into the DB. Insert-only + dedup-safe on product_link.
     */
    private function importJsonl(): int
    {
        $path = (string) $this->option('import-jsonl');
        $files = is_dir($path) ? glob(rtrim($path, '/\\') . '/*.jsonl') : [$path];
        if ($files === [] || $files === false) {
            $this->error("no .jsonl found at {$path}");

            return self::FAILURE;
        }
        $existing = DB::table('products')->where('website', 'goonet')->pluck('product_link')->flip();
        $this->info(count($existing) . ' goonet rows already present. Importing ' . count($files) . ' file(s) (batched)...');

        $batch = [];
        $flush = function () use (&$batch) {
            if ($batch !== []) {
                DB::table('products')->insert($batch);
                $this->inserted += count($batch);
                $batch = [];
            }
        };
        foreach ($files as $f) {
            $fh = fopen($f, 'r');
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (!is_array($row) || empty($row['product_link']) || isset($existing[$row['product_link']])) {
                    continue;
                }
                $existing[$row['product_link']] = true;
                $batch[] = $this->rowToInsert($row);
                if (count($batch) >= 500) {
                    $flush();
                    if ($this->inserted % 20000 < 500) {
                        $this->info("  inserted {$this->inserted}");
                    }
                }
            }
            fclose($fh);
        }
        $flush();
        // stock_code needs the auto-inc id, so set it in one pass after insert
        DB::statement("UPDATE products SET stock_code = CONCAT('GN', id) WHERE website='goonet' AND (stock_code IS NULL OR stock_code = '')");
        $this->info("IMPORT DONE: inserted {$this->inserted} goonet rows.");

        return self::SUCCESS;
    }

    /** map a crawl JSONL row to a products insert tuple (stock_code set in a later pass). */
    private function rowToInsert(array $r): array
    {
        $now = now();

        return [
            'title' => mb_substr($r['title'] ?? '', 0, 255),
            'model' => !empty($r['model']) ? mb_substr($r['model'], 0, 255) : null,
            'year' => $r['year'] ?? null,
            'mileage_km' => $r['mileage_km'] ?? null,
            'fuel' => $r['fuel'] ?? null,
            'transmission' => $r['transmission'] ?? null,
            'condition' => $r['condition'] ?? 'Used',
            'color' => $r['color'] ?? null,
            'body_style' => $r['body_style'] ?? null,
            'engine_cc' => $r['engine_cc'] ?? null,
            'drive_type' => $r['drive_type'] ?? null,
            'doors' => $r['doors'] ?? null,
            'steering' => $r['steering'] ?? null,
            'category_id' => $r['category_id'] ?? 20,
            'price' => (float) ($r['price_usd'] ?? 0),
            'website' => 'goonet',
            'country' => $r['country'] ?? 'Japan',
            'product_link' => $r['product_link'],
            'front_image' => $r['front_image'] ?? null,
            'front_image_source' => $r['front_image'] ?? null,
            'other_images' => json_encode($r['images'] ?? [], JSON_UNESCAPED_SLASHES),
            'other_images_source' => json_encode($r['images'] ?? [], JSON_UNESCAPED_SLASHES),
            'product_details' => $r['product_details'] ?? '',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /* ------------------------------------------------------------ chunk export */

    private function exportChunk(): int
    {
        $n = DB::table('products')->where('website', 'goonet')->count();
        if ($n === 0) {
            $this->error('no goonet rows — run --run first');

            return self::FAILURE;
        }
        $dir = base_path('db-export');
        @mkdir($dir, 0777, true);
        $sql = $dir . '/goonet-products.sql';
        $dump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        $cmd = '"' . $dump . '" -h 127.0.0.1 -P 3307 -u root --single-transaction --quick'
            . ' --no-create-info --skip-triggers --no-tablespaces --skip-lock-tables'
            . ' --skip-add-locks --skip-disable-keys'
            . ' --where="website=\'goonet\'" supreme_motors products';
        exec($cmd . ' > "' . $sql . '" 2>&1', $out, $rc);
        if ($rc !== 0 || !is_file($sql)) {
            $this->error('mysqldump failed: ' . implode("\n", $out));

            return self::FAILURE;
        }
        $header = "-- Supreme Motors — goo-net inventory chunk (atomic replace).\n"
            . "START TRANSACTION;\n"
            . "DELETE FROM `products` WHERE `website`='goonet';\n\n";
        file_put_contents($sql, $header . file_get_contents($sql) . "\nCOMMIT;\n");

        $zip = $dir . '/goonet-products.zip';
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $z->addFile($sql, 'goonet-products.sql');
        $z->close();
        @unlink($sql);
        $this->info('EXPORTED ' . number_format($n) . ' goonet rows -> ' . $zip
            . ' (' . round(filesize($zip) / 1024 / 1024, 1) . ' MB)');

        return self::SUCCESS;
    }
}
