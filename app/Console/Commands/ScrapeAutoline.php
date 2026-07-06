<?php

namespace App\Console\Commands;

use App\Models\Products;
use App\Services\AutolineDetailParser;
use App\Services\SchannelCurl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scrape autoline.info (the live Autoline / linemedia marketplace — autoline.com
 * is dead) into the products table.
 *
 * ENUMERATION is free and total: robots.txt exposes a sitemap index whose
 * sitemap-adverts-*.xml children list EVERY advert URL. Each URL ends with the
 * stable Autoline advert id (`--<digits>`), which is ALSO the `--<digits>.jpg`
 * suffix on that advert's images — so the id is our dedup key both against the
 * ~117k rows already in the DB and within a run.
 *
 * DETAIL pages carry a JSON-LD Product/Vehicle block + a visible spec table
 * (see AutolineDetailParser). autoline.info is NOT Cloudflare-gated, but we still
 * fetch through the shared curl.exe transport (SchannelCurl) for its cheap single
 * -Z parallel wave.
 *
 * SAFETY: this command only ever INSERTs (Products::create) autoline rows whose
 * advert id is not already present. It never updates or deletes an existing row,
 * so it cannot harm the production data. --preview writes NOTHING to the DB — it
 * renders a standalone HTML page of freshly fetched (unsaved) adverts for review.
 */
class ScrapeAutoline extends Command
{
    protected $signature = 'scrape:autoline
        {--enumerate : (re)build the advert-URL list from the sitemaps}
        {--preview=0 : fetch N NEW adverts and render a preview page — NO database writes}
        {--run : fetch NEW adverts and INSERT them (skips every already-present advert id)}
        {--pool=60 : concurrent detail fetches per wave}
        {--limit=0 : stop after inserting this many (0 = all)}
        {--usd-rate=1.08 : EUR->USD conversion for stored price}
        {--shard= : cursor/marker name for a sharded run}
        {--min-index=0 : start at this line of the URL list}
        {--max-index=0 : stop at this line (0 = end)}';

    protected $description = 'Scrape autoline.info adverts (sitemap-driven, dedup-safe, insert-only)';

    private const SITEMAP_INDEX = 'https://autoline.info/sitemap.xml';

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

    private SchannelCurl $curl;

    private AutolineDetailParser $parser;

    private string $jar;

    private string $stateDir;

    private int $inserted = 0;

    public function __construct(AutolineDetailParser $parser)
    {
        parent::__construct();
        $this->parser = $parser;
    }

    public function handle(): int
    {
        $this->stateDir = storage_path('app/cdn');
        @mkdir($this->stateDir, 0777, true);
        $this->curl = new SchannelCurl(null, sys_get_temp_dir() . '/autoline');
        $this->jar = $this->stateDir . '/autoline.jar';

        if ($this->option('enumerate')) {
            return $this->enumerate();
        }
        if ((int) $this->option('preview') > 0) {
            return $this->preview((int) $this->option('preview'));
        }
        if ($this->option('run')) {
            return $this->runInsert();
        }

        $this->error('choose one: --enumerate | --preview=N | --run');

        return self::FAILURE;
    }

    private function headers(): array
    {
        return ['User-Agent' => self::UA, 'Accept-Language' => 'en', 'Accept' => 'text/html,application/xhtml+xml'];
    }

    /* ---------------------------------------------------------------------- */
    /*  ENUMERATE                                                             */
    /* ---------------------------------------------------------------------- */

    private function enumerate(): int
    {
        $urlFile = $this->stateDir . '/autoline-urls.txt';
        [$idx, $body] = $this->curl->request('GET', self::SITEMAP_INDEX, $this->headers(), null, $this->jar, 40);
        if ($idx !== 200) {
            $this->error("sitemap index HTTP {$idx}");

            return self::FAILURE;
        }
        preg_match_all('#<loc>\s*(https://autoline\.info/sitemap-adverts-\d+\.xml)\s*</loc>#i', $body, $m);
        $advertMaps = array_values(array_unique($m[1]));
        $this->info(count($advertMaps) . ' advert sitemaps');

        $existing = $this->existingIds();
        $this->info(number_format(count($existing)) . ' advert ids already in DB (will be skipped)');

        $fh = fopen($urlFile, 'w');
        $seen = [];
        $total = 0;
        $new = 0;
        foreach ($advertMaps as $i => $sm) {
            [$s, $xml] = $this->curl->request('GET', $sm, $this->headers(), null, $this->jar, 60);
            if ($s !== 200) {
                $this->warn("  {$sm} HTTP {$s} — skipped");

                continue;
            }
            preg_match_all('#<loc>\s*(https://autoline\.info/-/[^<\s]+)\s*</loc>#i', $xml, $mm);
            foreach ($mm[1] as $u) {
                $id = $this->parser->listingId($u);
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $total++;
                if (isset($existing[$id])) {
                    continue;
                }
                fwrite($fh, $id . "\t" . $u . "\n");
                $new++;
            }
            if (($i + 1) % 10 === 0) {
                $this->info('  sitemap ' . ($i + 1) . '/' . count($advertMaps) . " — {$total} adverts, {$new} new");
            }
        }
        fclose($fh);
        $this->info("ENUMERATE DONE: {$total} adverts on site, {$new} NEW (not in DB) written to {$urlFile}");

        return self::SUCCESS;
    }

    /** advert ids already in the DB, taken from every autoline row's front_image */
    private function existingIds(): array
    {
        $ids = [];
        DB::table('products')->where('website', 'autoline')
            ->select('front_image')->orderBy('id')
            ->chunk(5000, function ($rows) use (&$ids) {
                foreach ($rows as $r) {
                    $id = $this->parser->listingIdFromImage((string) $r->front_image);
                    if ($id !== '') {
                        $ids[$id] = true;
                    }
                }
            });

        return $ids;
    }

    /* ---------------------------------------------------------------------- */
    /*  PREVIEW  (no DB writes)                                               */
    /* ---------------------------------------------------------------------- */

    private function preview(int $n): int
    {
        // candidate NEW urls: prefer the enumerated list, else pull one sitemap live
        $urls = $this->candidateUrls($n * 3);
        if ($urls === []) {
            $this->error('no candidate URLs');

            return self::FAILURE;
        }
        $existing = $this->existingIds();
        $picked = [];
        foreach ($urls as [$id, $u]) {
            if (!isset($existing[$id])) {
                $picked[] = [$id, $u];
            }
            if (count($picked) >= $n) {
                break;
            }
        }
        $this->info('fetching ' . count($picked) . ' NEW adverts for preview (no DB writes)…');

        $requests = [];
        foreach ($picked as $k => [$id, $u]) {
            $requests[$k] = ['method' => 'GET', 'url' => $u, 'headers' => $this->headers()];
        }
        $results = $this->curl->parallel($requests, $this->jar, min((int) $this->option('pool'), 30), 40);

        $rows = [];
        foreach ($picked as $k => [$id, $u]) {
            [$status, $html] = $results[$k] ?? [0, ''];
            if ($status !== 200 || $html === '') {
                continue;
            }
            $row = $this->parser->parse($html, $u);
            if ($row) {
                $row['category_id'] = $this->mapCategory($u, $row['title']);
                $row['price_usd'] = $row['price_eur'] !== null
                    ? round($row['price_eur'] * (float) $this->option('usd-rate')) : null;
                $rows[] = $row;
            }
        }

        $out = public_path('autoline-preview.html');
        file_put_contents($out, $this->renderPreview($rows));
        $this->info('PREVIEW written: ' . $out);
        $this->info('open: http://localhost/autoline-preview.html  (or your app host)/autoline-preview.html');
        $this->line('parsed ' . count($rows) . ' adverts — nothing was written to the database.');

        return self::SUCCESS;
    }

    /** @return list<array{0:string,1:string}> [id,url] */
    private function candidateUrls(int $want): array
    {
        $urlFile = $this->stateDir . '/autoline-urls.txt';
        $out = [];
        if (is_file($urlFile)) {
            $fh = fopen($urlFile, 'r');
            while (($line = fgets($fh)) !== false && count($out) < $want) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                [$id, $u] = array_pad(explode("\t", $line, 2), 2, '');
                if ($u !== '') {
                    $out[] = [$id, $u];
                }
            }
            fclose($fh);
            if ($out !== []) {
                return $out;
            }
        }
        // no list yet: pull the first advert sitemap live
        [$s, $xml] = $this->curl->request('GET', 'https://autoline.info/sitemap-adverts-1.xml', $this->headers(), null, $this->jar, 60);
        if ($s === 200) {
            preg_match_all('#<loc>\s*(https://autoline\.info/-/[^<\s]+)\s*</loc>#i', $xml, $mm);
            foreach ($mm[1] as $u) {
                $id = $this->parser->listingId($u);
                if ($id !== '') {
                    $out[] = [$id, $u];
                }
                if (count($out) >= $want) {
                    break;
                }
            }
        }

        return $out;
    }

    /* ---------------------------------------------------------------------- */
    /*  RUN  (INSERT-only)                                                    */
    /* ---------------------------------------------------------------------- */

    private function runInsert(): int
    {
        $urlFile = $this->stateDir . '/autoline-urls.txt';
        if (!is_file($urlFile)) {
            $this->error('no URL list — run --enumerate first');

            return self::FAILURE;
        }
        $shard = $this->option('shard') ? '-' . $this->option('shard') : '';
        $cursorFile = $this->stateDir . "/autoline{$shard}.cursor";
        $minIndex = (int) $this->option('min-index');
        $maxIndex = (int) $this->option('max-index');
        $limit = (int) $this->option('limit');
        $pool = max(4, (int) $this->option('pool'));
        $rate = (float) $this->option('usd-rate');

        $existing = $this->existingIds();
        $this->info(number_format(count($existing)) . ' existing advert ids loaded (skip-set)');

        $start = is_file($cursorFile) ? (int) file_get_contents($cursorFile) : $minIndex;
        $lines = file($urlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $end = $maxIndex > 0 ? min($maxIndex, count($lines)) : count($lines);
        $this->info("RUN lines {$start}..{$end} of " . count($lines));

        $i = $start;
        while ($i < $end) {
            $batch = [];
            for (; $i < $end && count($batch) < $pool; $i++) {
                [$id, $u] = array_pad(explode("\t", $lines[$i], 2), 2, '');
                if ($u === '' || isset($existing[$id])) {
                    continue;
                }
                $batch[$i] = [$id, $u];
            }
            if ($batch === []) {
                continue;
            }

            $requests = [];
            foreach ($batch as $k => [$id, $u]) {
                $requests[$k] = ['method' => 'GET', 'url' => $u, 'headers' => $this->headers()];
            }
            $results = $this->curl->parallel($requests, $this->jar, $pool, 40);

            foreach ($batch as $k => [$id, $u]) {
                [$status, $html] = $results[$k] ?? [0, ''];
                if ($status !== 200 || $html === '') {
                    continue;
                }
                $row = $this->parser->parse($html, $u);
                if (!$row || $row['listing_id'] === '' || isset($existing[$row['listing_id']])) {
                    continue;
                }
                $this->insertRow($row, $rate);
                $existing[$row['listing_id']] = true;   // guard within-run dupes too
            }

            file_put_contents($cursorFile, (string) $i);
            $this->info("  index {$i}/{$end} — inserted {$this->inserted}");
            if ($limit > 0 && $this->inserted >= $limit) {
                $this->info("hit --limit={$limit}");
                break;
            }
        }

        $this->info("RUN DONE: inserted {$this->inserted} new autoline adverts");

        return self::SUCCESS;
    }

    /** INSERT one advert. Never touches an existing row. */
    private function insertRow(array $row, float $rate): void
    {
        $priceUsd = $row['price_eur'] !== null ? round($row['price_eur'] * $rate) : 0.0;

        $attrs = [
            'title' => mb_substr($row['title'], 0, 255),
            'model' => $row['brand'] !== '' ? mb_substr($row['brand'], 0, 255) : null,
            'year' => $row['year'] ?? null,
            'mileage_km' => $row['mileage_km'] ?? null,
            'fuel' => isset($row['fuel']) ? mb_substr($row['fuel'], 0, 255) : null,
            'transmission' => isset($row['transmission']) ? mb_substr($row['transmission'], 0, 255) : null,
            'condition' => $row['condition'] !== '' ? $row['condition'] : null,
            'color' => isset($row['color']) ? mb_substr($row['color'], 0, 255) : null,
            'drive_type' => isset($row['drive_type']) ? mb_substr($row['drive_type'], 0, 255) : null,
            'axles' => $row['axles'] ?? null,
            'load_capacity_kg' => $row['load_capacity_kg'] ?? null,
            'power_hp' => $row['power_hp'] ?? null,
            'emission_standard' => isset($row['emission_standard']) ? mb_substr($row['emission_standard'], 0, 255) : null,
            'running_hours' => $row['running_hours'] ?? null,
            'seats' => $row['seats'] ?? null,
            'category_id' => $this->mapCategory($row['product_link'], $row['title']),
            'price' => $priceUsd,
            'website' => 'autoline',
            'country' => 'Europe',
            'product_link' => $row['product_link'],
            'front_image' => $row['front_image'],
            'front_image_source' => $row['front_image'],
            'other_images' => $row['images'],
            'other_images_source' => $row['images'],
            'product_details' => $row['product_details'],
        ];

        try {
            $product = Products::create($attrs);
            $product->update(['stock_code' => 'SM' . $product->id]);
            $this->inserted++;
        } catch (\Throwable $e) {
            $this->warn('insert failed for ' . $row['product_link'] . ': ' . $e->getMessage());
        }
    }

    /** map an autoline advert to one of the 11 category_ids the data already uses */
    private function mapCategory(string $url, string $title): int
    {
        $s = strtolower($url . ' ' . $title);
        $map = [
            // id => keywords (first match wins, order = specificity)
            56 => ['tractor-unit', 'truck-tractor', 'truck tractor'],
            13 => ['bus', 'coach'],
            19 => ['garbage', 'municipal', 'fire-truck', 'fire truck', 'sweeper', 'snow'],
            126 => ['airport'],
            129 => ['railway', 'locomotive'],
            130 => ['tank', 'fuel-tank', 'silo', 'bitumen'],
            128 => ['container'],
            12 => ['semi-trailer', 'semitrailer', 'semi trailer'],
            131 => ['trailer', 'drawbar'],
            4 => ['truck', 'lorry', 'dump', 'tipper', 'concrete', 'crane', 'chassis'],
            127 => ['van', 'camper', 'motorhome', 'commercial', 'pickup', 'minibus', 'car'],
        ];
        foreach ($map as $id => $kw) {
            foreach ($kw as $k) {
                if (str_contains($s, $k)) {
                    return $id;
                }
            }
        }

        return 127; // Commercial Vehicles fallback
    }

    /* ---------------------------------------------------------------------- */
    /*  PREVIEW RENDER                                                        */
    /* ---------------------------------------------------------------------- */

    private function renderPreview(array $rows): string
    {
        $usd = fn ($v) => $v === null ? '—' : '$' . number_format((float) $v, 0);
        $cards = '';
        foreach ($rows as $r) {
            $imgs = array_slice($r['images'], 0, 6);
            $thumbs = '';
            foreach ($imgs as $im) {
                $thumbs .= '<img loading="lazy" src="' . htmlspecialchars($im) . '">';
            }
            $cards .= '<article class="card">'
                . '<div class="gal">' . $thumbs . '</div>'
                . '<div class="body">'
                . '<h2>' . htmlspecialchars($r['title']) . '</h2>'
                . '<div class="meta"><span class="price">' . $usd($r['price_usd'] ?? null) . '</span>'
                . '<span class="eur">€' . number_format((float) ($r['price_eur'] ?? 0), 0) . '</span>'
                . '<span class="cat">cat ' . (int) $r['category_id'] . '</span>'
                . '<span class="imgs">' . count($r['images']) . ' imgs</span></div>'
                . '<div class="specs">' . ($r['product_details'] ?: '<em>no specs</em>') . '</div>'
                . '<a class="src" href="' . htmlspecialchars($r['product_link']) . '" target="_blank">source ↗ (id ' . htmlspecialchars($r['listing_id']) . ')</a>'
                . '</div></article>';
        }

        return '<!doctype html><meta charset="utf-8"><title>Autoline preview — ' . count($rows) . ' adverts</title>'
            . '<style>'
            . 'body{font-family:system-ui,Segoe UI,sans-serif;background:#0b1e3b;color:#e8eef7;margin:0;padding:24px}'
            . 'h1{font-size:20px}.note{color:#9db2d4;margin:0 0 20px;font-size:13px}'
            . '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:18px}'
            . '.card{background:#122c53;border:1px solid #1f3f6e;border-radius:14px;overflow:hidden}'
            . '.gal{display:flex;overflow-x:auto;gap:2px;background:#081733}'
            . '.gal img{height:150px;width:auto;object-fit:cover;flex:0 0 auto}'
            . '.body{padding:14px}h2{font-size:15px;margin:0 0 8px;line-height:1.3}'
            . '.meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:12px}'
            . '.price{font-size:18px;font-weight:800;color:#ff5a60}.eur{color:#9db2d4}'
            . '.cat,.imgs{background:#0b1e3b;padding:2px 8px;border-radius:20px;color:#9db2d4}'
            . '.specs{font-size:12px;color:#c7d4e8;max-height:170px;overflow:auto;background:#0e2547;padding:8px 12px;border-radius:8px}'
            . '.specs ul{margin:0;padding-left:16px}.specs li{margin:2px 0}'
            . '.src{display:inline-block;margin-top:10px;color:#7fb0ff;font-size:12px;text-decoration:none}'
            . '</style>'
            . '<h1>Autoline preview — ' . count($rows) . ' freshly-scraped adverts</h1>'
            . '<p class="note">These were fetched live from autoline.info and parsed with the new scraper. '
            . 'NOTHING here was written to the database — this is a dry-run preview for your review. '
            . 'Price shown in USD (EUR × ' . htmlspecialchars((string) $this->option('usd-rate')) . ') and original EUR.</p>'
            . '<div class="grid">' . $cards . '</div>';
    }
}
