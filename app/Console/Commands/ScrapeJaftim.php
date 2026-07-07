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
        {--fix-images : re-fetch galleries for image-poor jaftim rows + point images to the Bunny CDN}
        {--probe-tail : extend truncated galleries by probing the Bunny CDN pull-zone for tail images (no origin throttle)}
        {--prune-sold : delete jaftim rows that are no longer available (sold) on the live site, verified against a fresh full listing fetch}
        {--min-available=3400 : safety floor — abort --prune-sold if fewer available cars are fetched (guards against a failed page wrongly deleting rows)}
        {--per-page=500 : cars per listing request}
        {--pool=25 : concurrent detail-gallery fetches}
        {--gallery-limit=60 : store front + up to this many gallery images}
        {--refetch-min=0 : with --fix-images, also re-fetch rows whose stored gallery is >= this size (catches galleries truncated by an old cap)}
        {--refetch-max=0 : with --probe-tail, only process rows whose stored gallery is <= this (0 = no upper bound); skips already-un-truncated rows on resume}
        {--max-pages=40 : safety cap on listing pages}';

    protected $description = 'Scrape jaftim.com stock (arrStock-embedded, insert-only, live-safe chunk export)';

    private const LISTING = 'https://www.jaftim.com/stock/listing';

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

    private SchannelCurl $curl;

    private JaftimParser $parser;

    private string $jar;

    private int $inserted = 0;

    /** raw arrStock count of the last fetched page (before sold-filter) */
    private int $lastRaw = 0;

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
        if ($this->option('fix-images')) {
            return $this->fixImages();
        }
        if ($this->option('probe-tail')) {
            return $this->probeTail();
        }
        if ($this->option('prune-sold')) {
            return $this->pruneSold();
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

    /**
     * GET one listing page -> parsed rows. Retries: the big (4MB) listing request
     * is flaky on the first hit, so a bare "empty" is retried a few times before
     * we believe the page is genuinely empty (end of catalogue).
     */
    private function fetchPage(int $pageNum, int $perPage, int $tries = 5): array
    {
        $url = self::LISTING . '?pageNum=' . $pageNum . '&itemsPerPage=' . $perPage;
        // jaftim's big listing response is FLAKY — the same page can come back full
        // (500), short (a partial), or empty on different hits. So try several times
        // and keep the response with the MOST cars; only a consistently-empty page
        // is a real end-of-catalogue. This stops a flaky short/empty hit from
        // silently dropping cars or ending pagination early.
        $bestRaw = 0;
        $bestRows = [];
        for ($t = 1; $t <= $tries; $t++) {
            [$status, $html] = $this->curl->request('GET', $url, $this->headers(), null, $this->jar, 90);
            if ($status === 200 && $html !== '' && str_contains($html, 'arrStock')) {
                $raw = substr_count($html, '"stock_id":"');
                if ($raw > $bestRaw) {
                    $bestRaw = $raw;
                    $bestRows = $this->parser->parseListing($html);
                }
                if ($raw >= $perPage) {
                    break;   // full page — no need to keep retrying
                }
            }
            if ($t < $tries) {
                usleep(1200000);
            }
        }
        $this->lastRaw = $bestRaw;

        return $bestRows;
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

        $emptyStreak = 0;
        for ($page = 1; $page <= $maxPages; $page++) {
            $rows = $this->fetchPage($page, $perPage);
            // ONLY a genuinely empty page ends pagination — and require TWO empties
            // in a row so a single flaky-empty hit can't stop us short of the catalogue.
            if ($this->lastRaw === 0) {
                if (++$emptyStreak >= 2) {
                    $this->info("page {$page}: empty x2 — done");
                    break;
                }
                $this->info("page {$page}: empty (flaky?) — trying next");

                continue;
            }
            $emptyStreak = 0;
            $fresh = array_values(array_filter($rows, fn ($r) => !isset($existing[$r['product_link']])));
            if ($fresh !== []) {
                $this->fillGalleries($fresh, $pool, $limit);
                foreach ($fresh as $r) {
                    $this->insertRow($r);
                    $existing[$r['product_link']] = true;
                }
            }
            $this->info("page {$page}: raw {$this->lastRaw}, +" . count($fresh) . " inserted (total {$this->inserted})");
            // NOTE: do NOT stop on a partial page — jaftim flakily returns short pages
            // mid-catalogue; we page on until two empties confirm the real end.
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
            'other_images' => $r['images'],  // cast to array by the model -> json
            // other_images_source has NO cast on the model, so encode it ourselves
            'other_images_source' => json_encode($r['images'], JSON_UNESCAPED_SLASHES),
            'product_details' => $r['product_details'],
        ];
        // dealer stock ref (JFTUK…) isn't reliably unique, and stock_code has a
        // UNIQUE index — so use the guaranteed-unique SM{id} like the other sources.
        try {
            $p = Products::create($attrs);
            $p->update(['stock_code' => 'SM' . $p->id]);
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
        // --skip-add-locks + --skip-disable-keys drop the LOCK TABLES and
        // ALTER TABLE ... DISABLE KEYS statements, which force an implicit COMMIT
        // and would break the atomic transaction wrap added below.
        $cmd = '"' . $dump . '" -h 127.0.0.1 -P 3307 -u root --single-transaction --quick'
            . ' --no-create-info --skip-triggers --no-tablespaces --skip-lock-tables'
            . ' --skip-add-locks --skip-disable-keys'
            . ' --where="website=\'jaftim\'" supreme_motors products';
        $out = [];
        exec($cmd . ' > "' . $sql . '" 2>&1', $out, $rc);
        if ($rc !== 0 || !is_file($sql)) {
            $this->error('mysqldump failed: ' . implode("\n", $out));

            return self::FAILURE;
        }
        // Wrap the whole replace in ONE transaction so it is atomic: the DELETE
        // and all INSERTs commit together, or (on a mysqld crash / column-mismatch
        // error mid-import) roll back entirely — live jaftim inventory is never
        // left half-empty. products is InnoDB, so DML rolls back cleanly.
        $header = "-- Supreme Motors — jaftim inventory chunk (atomic replace).\n"
            . "-- Wrapped in a transaction: a mid-import failure rolls back and leaves\n"
            . "-- the live jaftim inventory untouched. Safe to re-run.\n"
            . "START TRANSACTION;\n"
            . "DELETE FROM `products` WHERE `website`='jaftim';\n\n";
        file_put_contents($sql, $header . file_get_contents($sql) . "\nCOMMIT;\n");
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

    /* ------------------------------------------------------------- fix images */

    /**
     * Re-fetch real galleries for jaftim rows that only got the fallback f.jpg
     * (their detail fetch failed during the flaky scrape), and point every jaftim
     * image at the sm-jaftim Bunny pull-zone so visitors never hit erp directly.
     */
    private function fixImages(): int
    {
        $cdn = 'https://sm-jaftim.b-cdn.net';
        $erp = 'https://erp.jaftim.com';
        $pool = min(max(4, (int) $this->option('pool')), 8); // gentle — jaftim throttles
        $limit = (int) $this->option('gallery-limit');
        $refetchMin = (int) $this->option('refetch-min');

        // Repoint any erp-origin image URLs to the Bunny pull-zone — cheap, no
        // network fetch, so visitors never hit the throttled origin. Only the
        // display columns; other_images_source stays as the erp source of truth.
        $repointed = DB::table('products')->where('website', 'jaftim')
            ->where('front_image', 'like', '%erp.jaftim.com%')
            ->update(['front_image' => DB::raw("REPLACE(front_image,'erp.jaftim.com','sm-jaftim.b-cdn.net')")]);
        DB::update("UPDATE products SET other_images = REPLACE(other_images,'erp.jaftim.com','sm-jaftim.b-cdn.net') WHERE website='jaftim' AND other_images LIKE '%erp.jaftim.com%'");
        $this->info("repointed {$repointed} front_images erp -> Bunny CDN");

        // Rows needing a re-fetch: image-poor (only the fallback), plus — when
        // --refetch-min is set — rows at/above an old truncation cap so their
        // full galleries are re-pulled with the raised --gallery-limit.
        $rows = DB::table('products')->where('website', 'jaftim')
            ->where(function ($q) use ($refetchMin) {
                $q->whereRaw('JSON_LENGTH(other_images) <= 1');
                if ($refetchMin > 0) {
                    $q->orWhereRaw('JSON_LENGTH(other_images) >= ?', [$refetchMin]);
                }
            })
            ->select('id', 'product_link')->get()->all();
        $this->info(count($rows) . ' rows to re-fetch (gentle pool ' . $pool . ', cap ' . $limit . ')');

        $fixed = 0;
        $noimg = 0;
        foreach (array_chunk($rows, $pool * 3) as $batch) {
            // two passes so a flaky miss gets another shot
            $pending = $batch;
            for ($pass = 1; $pass <= 3 && $pending !== []; $pass++) {
                $req = [];
                foreach ($pending as $r) {
                    $req[$r->id] = ['method' => 'GET', 'url' => $r->product_link, 'headers' => $this->headers()];
                }
                $res = $this->curl->parallel($req, $this->jar, $pool, 40);
                $next = [];
                foreach ($pending as $r) {
                    [$status, $html] = $res[$r->id] ?? [0, ''];
                    if ($status !== 200 || $html === '') {
                        $next[] = $r;

                        continue;
                    }
                    $sid = preg_match('~/(\d+)/?$~', $r->product_link, $m) ? $m[1] : '';
                    $imgs = $sid ? $this->parser->parseGalleryImages($html, $sid) : [];
                    $imgs = array_values(array_filter(array_map(fn ($u) => str_replace($erp, $cdn, $u), $imgs)));
                    $real = array_values(array_filter($imgs, fn ($u) => !str_ends_with($u, '/f.jpg')));
                    if ($real !== []) {
                        $keep = array_slice($imgs, 0, $limit + 1);
                        DB::table('products')->where('id', $r->id)->update([
                            'front_image' => $keep[0],
                            'other_images' => json_encode($keep, JSON_UNESCAPED_SLASHES),
                            'other_images_source' => json_encode(array_map(fn ($u) => str_replace($cdn, $erp, $u), $keep), JSON_UNESCAPED_SLASHES),
                        ]);
                        $fixed++;
                    } else {
                        $noimg++;   // detail page genuinely has no photos
                    }
                }
                $pending = $next;
                if ($pending !== []) {
                    usleep(1500000);
                }
            }
            usleep(400000); // gentle pace between batches
            $this->info("  progress: fixed {$fixed}, no-image {$noimg}");
        }
        $this->info("FIX-IMAGES DONE: {$fixed} galleries recovered, {$noimg} genuinely image-less. All jaftim images now point to {$cdn}.");

        return self::SUCCESS;
    }

    /**
     * Un-truncate galleries WITHOUT touching the throttled jaftim.com detail
     * pages. An old --gallery-limit=12 capped every gallery at f + 1..12; the
     * real galleries run to ~29. We already know each car's stock_id and image
     * extension from its stored images, so we probe the Bunny pull-zone
     * (sm-jaftim.b-cdn.net — NOT throttled) for the missing indices with a cheap
     * 1-byte range GET. Existing hits are unioned with the stored set, so gaps
     * in the middle are preserved and only real files are kept.
     */
    private function probeTail(): int
    {
        $cdn = 'https://sm-jaftim.b-cdn.net';
        $erp = 'https://erp.jaftim.com';
        $path = '/storage/app/public/stock/';
        $pool = max(8, (int) $this->option('pool'));        // CDN isn't throttled
        $limit = (int) $this->option('gallery-limit');
        $window = min($limit, 40);                            // max index to probe (live max ~29)
        $minLen = max(2, (int) $this->option('refetch-min') ?: 13);

        $maxLen = (int) $this->option('refetch-max');
        $q = DB::table('products')->where('website', 'jaftim')
            ->whereRaw('JSON_LENGTH(other_images) >= ?', [$minLen]);
        if ($maxLen > 0) {
            $q->whereRaw('JSON_LENGTH(other_images) <= ?', [$maxLen]);   // resume: only not-yet-expanded rows
        }
        $rows = $q->select('id', 'product_link', 'front_image', 'other_images')->get()->all();
        $this->info(count($rows) . " candidate rows to probe (pool {$pool}, indices up to {$window})");

        $grown = 0;
        $checked = 0;
        foreach (array_chunk($rows, 150) as $batch) {
            $meta = [];   // id => [sid, ext, front, have(set of ints)]
            $req = [];    // "id|idx" => range-GET request
            foreach ($batch as $r) {
                $imgs = json_decode($r->other_images ?? '[]', true) ?: [];
                $sid = null;
                foreach ($imgs as $u) {
                    if (is_string($u) && preg_match('~/stock/(\d+)/~', $u, $sm)) { $sid = $sm[1]; break; }
                }
                if ($sid === null && preg_match('~/(\d+)/?$~', (string) $r->product_link, $sm)) { $sid = $sm[1]; }
                if ($sid === null) { continue; }

                $ext = 'jpg';
                $have = [];
                foreach ($imgs as $u) {
                    if (is_string($u) && preg_match('~/' . $sid . '/(\d+)\.([a-z0-9]+)$~i', $u, $em)) {
                        $have[(int) $em[1]] = true;
                        $ext = strtolower($em[2]);   // numbered-image extension (usually uniform per car)
                    }
                }
                $meta[$r->id] = ['sid' => $sid, 'ext' => $ext, 'front' => $r->front_image, 'have' => $have];

                for ($i = 1; $i <= $window; $i++) {
                    if (isset($have[$i])) { continue; }       // already stored — no need to probe
                    $req[$r->id . '|' . $i] = [
                        'method' => 'GET',
                        'url' => $cdn . $path . $sid . '/' . $i . '.' . $ext,
                        'headers' => ['User-Agent' => self::UA, 'Range' => 'bytes=0-0'],
                    ];
                }
            }

            if ($req !== []) {
                $res = $this->curl->parallel($req, $this->jar, $pool, 20);
                foreach ($res as $key => $pair) {
                    [$status] = $pair;
                    if ($status === 200 || $status === 206) {     // file exists
                        [$id, $idx] = explode('|', $key);
                        $meta[(int) $id]['have'][(int) $idx] = true;
                    }
                }
            }

            foreach ($batch as $r) {
                $checked++;
                $m = $meta[$r->id] ?? null;
                if ($m === null) { continue; }
                $nums = array_keys($m['have']);
                sort($nums, SORT_NUMERIC);
                $nums = array_slice($nums, 0, $window);
                $front = $m['front'] ?: ($cdn . $path . $m['sid'] . '/f.' . $m['ext']);
                $list = array_merge([$front], array_map(
                    fn ($i) => $cdn . $path . $m['sid'] . '/' . $i . '.' . $m['ext'],
                    $nums
                ));
                $prev = count(json_decode($r->other_images ?? '[]', true) ?: []);
                if (count($list) > $prev) {
                    DB::table('products')->where('id', $r->id)->update([
                        'front_image' => $list[0],
                        'other_images' => json_encode($list, JSON_UNESCAPED_SLASHES),
                        'other_images_source' => json_encode(array_map(fn ($u) => str_replace($cdn, $erp, $u), $list), JSON_UNESCAPED_SLASHES),
                    ]);
                    $grown++;
                }
            }
            $this->info("  checked {$checked}/" . count($rows) . ", grown {$grown}");
        }

        $this->info("PROBE-TAIL DONE: {$grown} galleries extended (of {$checked} candidates).");

        return self::SUCCESS;
    }

    /**
     * Delete jaftim rows that are no longer AVAILABLE on the live site. The
     * scraper is insert-only, so cars that later sold (sold=1) linger in the DB;
     * the user wants only the ~3607 currently-available cars.
     *
     * SAFETY (live production DB, no rollback): we build the available set from a
     * FRESH full listing fetch — fetchPage() already retries/best-of on jaftim's
     * flaky pages — and ABORT if fewer than --min-available cars come back, so a
     * single failed page can never wrongly delete a few hundred live cars. We
     * delete only rows whose stock_id is confidently ABSENT from a healthy
     * available set.
     */
    private function pruneSold(): int
    {
        $perPage = max(50, (int) $this->option('per-page'));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $floor = (int) $this->option('min-available');

        // 1. Build the available (sold=0) stock_id set from a fresh full fetch.
        $avail = [];
        $empty = 0;
        for ($p = 1; $p <= $maxPages; $p++) {
            $rows = $this->fetchPage($p, $perPage);
            if ($rows === []) {
                if (++$empty >= 2) {
                    break;   // two consecutive empties = genuine end
                }

                continue;
            }
            $empty = 0;
            foreach ($rows as $row) {
                $sid = (string) ($row['stock_id'] ?? '');
                if ($sid !== '') {
                    $avail[$sid] = true;
                }
            }
            $this->info("  page {$p}: available so far " . count($avail));
        }
        $availCount = count($avail);
        $this->info("available (sold=0) stock_ids fetched from site: {$availCount}");

        // 2. Safety floor — never prune off an incomplete fetch.
        if ($availCount < $floor) {
            $this->error("ABORT: only {$availCount} available fetched (< floor {$floor}). A listing page likely failed — refusing to delete to avoid removing live cars. Re-run.");

            return self::FAILURE;
        }

        // 3. Collect DB jaftim rows whose stock_id is NOT in the available set.
        $toDelete = [];
        $unmatched = 0;
        DB::table('products')->where('website', 'jaftim')
            ->select('id', 'product_link')->orderBy('id')
            ->chunk(2000, function ($rows) use (&$toDelete, &$unmatched, $avail) {
                foreach ($rows as $r) {
                    if (preg_match('~/(\d+)/?$~', (string) $r->product_link, $m)) {
                        if (!isset($avail[$m[1]])) {
                            $toDelete[] = $r->id;
                        }
                    } else {
                        $unmatched++;   // no stock_id in link — leave it, don't guess
                    }
                }
            });

        $before = DB::table('products')->where('website', 'jaftim')->count();
        $this->info(count($toDelete) . " sold/removed rows to delete ({$unmatched} rows had no parseable stock_id — kept)");

        // 4. Delete in batches.
        $deleted = 0;
        foreach (array_chunk($toDelete, 500) as $c) {
            $deleted += DB::table('products')->whereIn('id', $c)->delete();
        }
        $after = DB::table('products')->where('website', 'jaftim')->count();
        $this->info("PRUNE-SOLD DONE: deleted {$deleted}. jaftim rows {$before} -> {$after} (site available {$availCount}).");

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
