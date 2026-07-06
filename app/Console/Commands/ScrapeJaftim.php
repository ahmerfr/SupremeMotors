<?php

namespace App\Console\Commands;

use App\Models\Products;
use App\Services\JaftimParser;
use App\Services\SchannelCurl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scrape jaftim.com/stock/listing into the products table (website = 'jaftim').
 *
 * FAST: the listing page embeds every car's full record as `var arrStock=[...]`, so
 * the whole ~4k-car catalogue is read in ~8 requests at itemsPerPage=500 — no
 * per-car detail fetch for the DATA. A second concurrent pass fetches each detail
 * page only for the GALLERY image list.
 *
 * SAFE FOR A LIVE DB: this command writes only to the LOCAL database. --run inserts
 * new website='jaftim' rows (skipping any product_link already present); it never
 * touches other rows. --export-chunk then dumps ONLY the jaftim rows to a zip you
 * import into the live DB via phpMyAdmin. --preview writes nothing at all.
 */
class ScrapeJaftim extends Command
{
    protected $signature = 'scrape:jaftim
        {--preview=0 : fetch N cars (page 1) + galleries and render a preview page — NO DB writes}
        {--run : fetch all pages and INSERT new jaftim rows into the LOCAL db}
        {--export-chunk : dump website=jaftim rows to db-export/jaftim-*.zip for live import}
        {--per-page=500 : cars per listing request}
        {--pool=25 : concurrent detail-gallery fetches}
        {--gallery-limit=12 : store front + up to this many gallery images}
        {--max-pages=40 : safety cap on listing pages}';

    protected $description = 'Scrape jaftim.com stock (arrStock-embedded, insert-only, live-safe chunk export)';

    private const LISTING = 'https://www.jaftim.com/stock/listing';

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

    private SchannelCurl $curl;

    private JaftimParser $parser;

    private string $jar;

    private int $inserted = 0;

    public function __construct(JaftimParser $parser)
    {
        parent::__construct();
        $this->parser = $parser;
    }

    public function handle(): int
    {
        $this->curl = new SchannelCurl(null, sys_get_temp_dir() . '/jaftim');
        $this->jar = storage_path('app/cdn/jaftim.jar');
        @mkdir(dirname($this->jar), 0777, true);

        if ($this->option('export-chunk')) {
            return $this->exportChunk();
        }
        if ((int) $this->option('preview') > 0) {
            return $this->preview((int) $this->option('preview'));
        }
        if ($this->option('run')) {
            return $this->runInsert();
        }
        $this->error('choose one: --preview=N | --run | --export-chunk');

        return self::FAILURE;
    }

    private function headers(): array
    {
        return ['User-Agent' => self::UA, 'Accept-Language' => 'en', 'Accept' => 'text/html'];
    }

    /** GET one listing page -> parsed rows */
    private function fetchPage(int $pageNum, int $perPage): array
    {
        $url = self::LISTING . '?pageNum=' . $pageNum . '&itemsPerPage=' . $perPage;
        [$status, $html] = $this->curl->request('GET', $url, $this->headers(), null, $this->jar, 90);
        if ($status !== 200 || $html === '') {
            return [];
        }

        return $this->parser->parseListing($html);
    }

    /**
     * Fill other_images for a batch of rows by fetching each detail page concurrently.
     *
     * @param  array<int,array<string,mixed>>  $rows  (modified in place)
     */
    private function fillGalleries(array &$rows, int $pool, int $limit): void
    {
        $requests = [];
        foreach ($rows as $i => $r) {
            $requests[$i] = ['method' => 'GET', 'url' => $r['product_link'], 'headers' => $this->headers()];
        }
        $results = $this->curl->parallel($requests, $this->jar, $pool, 40);
        foreach ($rows as $i => &$r) {
            [$status, $html] = $results[$i] ?? [0, ''];
            if ($status === 200 && $html !== '') {
                $imgs = $this->parser->parseGalleryImages($html, $r['stock_id']);
                if ($imgs !== []) {
                    $r['images'] = array_slice($imgs, 0, $limit + 1);
                    $r['front_image'] = $imgs[0];
                }
            }
        }
        unset($r);
    }

    /* ---------------------------------------------------------------- preview */

    private function preview(int $n): int
    {
        $this->info('fetching page 1 …');
        $rows = $this->fetchPage(1, max($n, 20));
        if ($rows === []) {
            $this->error('no cars parsed');

            return self::FAILURE;
        }
        $rows = array_slice($rows, 0, $n);
        $this->info('fetching ' . count($rows) . ' galleries …');
        $this->fillGalleries($rows, min((int) $this->option('pool'), 12), (int) $this->option('gallery-limit'));

        $out = public_path('jaftim-preview.html');
        file_put_contents($out, $this->render($rows));
        $this->info('PREVIEW written: ' . $out . '  (nothing written to the database)');
        $this->line('parsed ' . count($rows) . ' cars.');

        return self::SUCCESS;
    }

    /* -------------------------------------------------------------------- run */

    private function runInsert(): int
    {
        $perPage = max(50, (int) $this->option('per-page'));
        $pool = max(4, (int) $this->option('pool'));
        $limit = (int) $this->option('gallery-limit');
        $maxPages = (int) $this->option('max-pages');

        // existing jaftim product_links -> skip set (dedup / resumable, insert-only)
        $existing = [];
        DB::table('products')->where('website', 'jaftim')->select('product_link')
            ->orderBy('id')->chunk(5000, function ($rs) use (&$existing) {
                foreach ($rs as $r) {
                    $existing[$r->product_link] = true;
                }
            });
        $this->info(count($existing) . ' jaftim rows already present (will skip)');

        for ($page = 1; $page <= $maxPages; $page++) {
            $rows = $this->fetchPage($page, $perPage);
            if ($rows === []) {
                $this->info("page {$page}: empty — done");
                break;
            }
            $fresh = array_values(array_filter($rows, fn ($r) => !isset($existing[$r['product_link']])));
            if ($fresh === []) {
                $this->info("page {$page}: all " . count($rows) . ' already present');

                continue;
            }
            $this->fillGalleries($fresh, $pool, $limit);
            foreach ($fresh as $r) {
                $this->insertRow($r);
                $existing[$r['product_link']] = true;
            }
            $this->info("page {$page}: +" . count($fresh) . " inserted (total {$this->inserted})");
            if (count($rows) < $perPage) {
                $this->info('last page reached');
                break;
            }
        }
        $this->info("RUN DONE: inserted {$this->inserted} jaftim cars. Next: php artisan scrape:jaftim --export-chunk");

        return self::SUCCESS;
    }

    /** INSERT one car. Never touches an existing row. */
    private function insertRow(array $r): void
    {
        $attrs = [
            'title' => mb_substr($r['title'], 0, 255),
            'model' => $r['model'] !== '' ? mb_substr($r['model'], 0, 255) : null,
            'year' => $r['year'],
            'mileage_km' => $r['mileage_km'],
            'fuel' => $r['fuel'] ? mb_substr($r['fuel'], 0, 255) : null,
            'transmission' => $r['transmission'] ? mb_substr($r['transmission'], 0, 255) : null,
            'condition' => $r['condition'],
            'color' => $r['color'] ? mb_substr($r['color'], 0, 255) : null,
            'body_style' => $r['body_style'] ? mb_substr($r['body_style'], 0, 255) : null,
            'engine_cc' => $r['engine_cc'],
            'drive_type' => $r['drive_type'] ? mb_substr($r['drive_type'], 0, 255) : null,
            'doors' => $r['doors'],
            'steering' => $r['steering'],
            'category_id' => $r['category_id'],
            'price' => (float) $r['price_usd'],
            'website' => 'jaftim',
            'country' => $r['country'],
            'product_link' => $r['product_link'],
            'front_image' => $r['front_image'],
            'front_image_source' => $r['front_image'],
            'other_images' => $r['images'],
            'other_images_source' => $r['images'],
            'product_details' => $r['product_details'],
        ];
        try {
            $p = Products::create($attrs);
            $p->update(['stock_code' => $r['stock_ref'] ?: ('SM' . $p->id)]);
            $this->inserted++;
        } catch (\Throwable $e) {
            $this->warn('insert failed ' . $r['product_link'] . ': ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------ chunk export */

    private function exportChunk(): int
    {
        $n = DB::table('products')->where('website', 'jaftim')->count();
        if ($n === 0) {
            $this->error('no jaftim rows to export — run --run first');

            return self::FAILURE;
        }
        $dir = base_path('db-export');
        @mkdir($dir, 0777, true);
        $sql = $dir . '/jaftim-products.sql';
        $dump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        // INSERT-only dump of just the jaftim rows (no CREATE — table exists on live)
        $cmd = '"' . $dump . '" -h 127.0.0.1 -P 3307 -u root --single-transaction --quick'
            . ' --no-create-info --skip-triggers --no-tablespaces --skip-lock-tables'
            . ' --where="website=\'jaftim\'" supreme_motors products';
        $out = [];
        exec($cmd . ' > "' . $sql . '" 2>&1', $out, $rc);
        if ($rc !== 0 || !is_file($sql)) {
            $this->error('mysqldump failed: ' . implode("\n", $out));

            return self::FAILURE;
        }
        $zip = $dir . '/jaftim-products.zip';
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $z->addFile($sql, 'jaftim-products.sql');
        $z->close();
        @unlink($sql);
        $this->info('EXPORTED ' . number_format($n) . ' jaftim rows -> ' . $zip
            . ' (' . round(filesize($zip) / 1024 / 1024, 1) . ' MB)');
        $this->line('Import on live: phpMyAdmin -> Import -> jaftim-products.zip  (pure INSERTs, adds only new rows)');

        return self::SUCCESS;
    }

    /* ----------------------------------------------------------------- render */

    private function render(array $rows): string
    {
        $cards = '';
        foreach ($rows as $r) {
            $thumbs = '';
            foreach (array_slice($r['images'], 0, 6) as $im) {
                $thumbs .= '<img loading="lazy" src="' . htmlspecialchars($im) . '" onerror="this.style.display=\'none\'">';
            }
            $price = $r['price_usd'] > 0 ? '$' . number_format($r['price_usd']) : 'Enquire';
            $cards .= '<article class="card"><div class="gal">' . $thumbs . '</div><div class="body">'
                . '<h2>' . htmlspecialchars($r['title']) . '</h2>'
                . '<div class="meta"><span class="price">' . $price . '</span>'
                . '<span class="tag">' . htmlspecialchars((string) ($r['mileage_km'] ?? '—')) . ' km</span>'
                . '<span class="tag">' . htmlspecialchars((string) ($r['fuel'] ?? '')) . '</span>'
                . '<span class="tag">' . htmlspecialchars((string) ($r['transmission'] ?? '')) . '</span>'
                . '<span class="tag">cat ' . (int) $r['category_id'] . '</span>'
                . '<span class="tag">' . count($r['images']) . ' imgs</span></div>'
                . '<div class="specs">' . ($r['product_details'] ?: '<em>no specs</em>') . '</div>'
                . '<a class="src" href="' . htmlspecialchars($r['product_link']) . '" target="_blank">source ↗ (' . htmlspecialchars((string) ($r['stock_ref'] ?? $r['stock_id'])) . ')</a>'
                . '</div></article>';
        }

        return '<!doctype html><meta charset="utf-8"><title>Jaftim preview — ' . count($rows) . ' cars</title><style>'
            . 'body{font-family:system-ui,Segoe UI,sans-serif;background:#0b1e3b;color:#e8eef7;margin:0;padding:24px}'
            . 'h1{font-size:20px}.note{color:#9db2d4;font-size:13px;margin:0 0 18px}'
            . '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:18px}'
            . '.card{background:#122c53;border:1px solid #1f3f6e;border-radius:14px;overflow:hidden}'
            . '.gal{display:flex;overflow-x:auto;gap:2px;background:#081733}.gal img{height:150px;object-fit:cover;flex:0 0 auto}'
            . '.body{padding:14px}h2{font-size:15px;margin:0 0 8px;line-height:1.3}'
            . '.meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:12px}'
            . '.price{font-size:18px;font-weight:800;color:#ff5a60}.tag{background:#0b1e3b;padding:2px 8px;border-radius:20px;color:#9db2d4}'
            . '.specs{font-size:12px;color:#c7d4e8;max-height:150px;overflow:auto;background:#0e2547;padding:8px 12px;border-radius:8px}'
            . '.specs ul{margin:0;padding-left:16px}.src{display:inline-block;margin-top:10px;color:#7fb0ff;font-size:12px;text-decoration:none}'
            . '</style><h1>Jaftim preview — ' . count($rows) . ' freshly-scraped cars</h1>'
            . '<p class="note">Live from jaftim.com, parsed with the new scraper. NOTHING written to the database — dry-run for review. Prices are the site\'s displayed USD.</p>'
            . '<div class="grid">' . $cards . '</div>';
    }
}
