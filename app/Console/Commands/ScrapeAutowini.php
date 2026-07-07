<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * autowini.com — Korean used-car export inventory (~207k cars).
 *
 * The crawl runs on GitHub runners via scripts/autowini_crawl.py (public /items
 * JSON + detail-page spec block + gallery). This command only ingests the JSONL
 * the runners produce and builds the live-import chunks. website='autowini', so
 * it is fully isolated from goo-net / jaftim — its DELETE and chunks never touch
 * another source's rows.
 */
class ScrapeAutowini extends Command
{
    protected $signature = 'scrape:autowini
        {--import-jsonl= : load crawl JSONL file(s) (a dir or one file) into the DB, insert-only + dedup-safe}
        {--export-chunk : dump website=autowini rows to db-export/autowini-*.zip for live import}
        {--chunk-rows=6000 : rows per SQL chunk file for --export-chunk}';

    protected $description = 'Import autowini crawl JSONL and export upload-friendly SQL chunks';

    private int $inserted = 0;

    public function handle(): int
    {
        if ($this->option('import-jsonl')) {
            return $this->importJsonl();
        }
        if ($this->option('export-chunk')) {
            return $this->exportChunk();
        }
        $this->error('choose one: --import-jsonl=<file|dir> | --export-chunk');

        return self::FAILURE;
    }

    private function importJsonl(): int
    {
        $path = (string) $this->option('import-jsonl');
        $files = is_dir($path) ? glob(rtrim($path, '/\\') . '/*.jsonl') : [$path];
        if ($files === [] || $files === false) {
            $this->error("no .jsonl found at {$path}");

            return self::FAILURE;
        }
        $existing = DB::table('products')->where('website', 'autowini')->pluck('product_link')->flip();
        $this->info(count($existing) . ' autowini rows already present. Importing ' . count($files) . ' file(s) (batched)...');

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
                if (count($batch) >= 100) {
                    $flush();
                    if ($this->inserted % 20000 < 100) {
                        $this->info("  inserted {$this->inserted}");
                    }
                }
            }
            fclose($fh);
        }
        $flush();
        DB::statement("UPDATE products SET stock_code = CONCAT('AW', id) WHERE website='autowini' AND (stock_code IS NULL OR stock_code = '')");
        $this->info("IMPORT DONE: inserted {$this->inserted} autowini rows.");

        return self::SUCCESS;
    }

    /** map an autowini crawl JSONL row to a products insert tuple. */
    private function rowToInsert(array $r): array
    {
        $now = now();

        return [
            'title' => mb_substr($r['title'] ?? '', 0, 255),
            'model' => !empty($r['model']) ? mb_substr($r['model'], 0, 255) : null,
            'model_code' => !empty($r['item_code']) ? mb_substr($r['item_code'], 0, 60) : null,
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
            'seats' => $r['seats'] ?? null,
            'steering' => $r['steering'] ?? null,
            'category_id' => $r['category_id'] ?? 20,
            'price' => (float) ($r['price_usd'] ?? $r['price'] ?? 0),
            'website' => 'autowini',
            'country' => $r['country'] ?? 'South Korea',
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

    /**
     * Export autowini inventory as upload-friendly gzipped SQL chunks (identical
     * mechanics to the goo-net exporter): N files of --chunk-rows each, extended
     * inserts (300/stmt), own transaction per file, DELETE only in chunk 1, a
     * finalize file rebuilds stock_code. id/stock_code omitted so re-insert can't
     * collide with existing live ids.
     */
    private function exportChunk(): int
    {
        $n = DB::table('products')->where('website', 'autowini')->count();
        if ($n === 0) {
            $this->error('no autowini rows — import first');

            return self::FAILURE;
        }
        $chunkRows = max(1000, (int) ($this->option('chunk-rows') ?: 6000));
        $perStmt = 300;

        $cols = array_values(array_filter(
            Schema::getColumnListing('products'),
            fn ($c) => !in_array($c, ['id', 'stock_code'], true)
        ));
        $colList = implode(', ', array_map(fn ($c) => "`$c`", $cols));
        $pdo = DB::connection()->getPdo();
        $q = fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v);

        $dir = base_path('db-export');
        $work = $dir . '/autowini-chunks';
        if (is_dir($work)) {
            array_map('unlink', (array) glob($work . '/*'));
        }
        @mkdir($work, 0777, true);

        $files = (int) ceil($n / $chunkRows);
        $this->info("Exporting " . number_format($n) . " rows -> {$files} chunk(s) of {$chunkRows} + finalize...");

        $fileIdx = 0;
        $written = 0;
        $fh = null;
        $rowInStmt = 0;

        $openFile = function () use (&$fh, &$fileIdx, &$rowInStmt, $work, $files, $colList) {
            $fileIdx++;
            $name = sprintf('%s/autowini-%02dof%02d.sql.gz', $work, $fileIdx, $files);
            $fh = gzopen($name, 'wb6');
            gzwrite($fh, "-- Supreme Motors autowini inventory — chunk {$fileIdx}/{$files}. Import in order.\n");
            gzwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n");
            if ($fileIdx === 1) {
                gzwrite($fh, "DELETE FROM `products` WHERE `website`='autowini';\n");
            }
            $rowInStmt = 0;
        };
        $closeStmt = function () use (&$fh, &$rowInStmt) {
            if ($rowInStmt > 0) {
                gzwrite($fh, ";\n");
                $rowInStmt = 0;
            }
        };
        $closeFile = function () use (&$fh, $closeStmt) {
            if ($fh) {
                $closeStmt();
                gzwrite($fh, "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n");
                gzclose($fh);
                $fh = null;
            }
        };

        $openFile();
        DB::table('products')->where('website', 'autowini')->orderBy('id')
            ->each(function ($r) use (&$fh, &$written, &$rowInStmt, $cols, $colList, $q, $chunkRows, $perStmt, $openFile, $closeFile, $closeStmt) {
                if ($written > 0 && $written % $chunkRows === 0) {
                    $closeFile();
                    $openFile();
                }
                if ($rowInStmt === 0) {
                    gzwrite($fh, "INSERT INTO `products` ({$colList}) VALUES\n");
                } else {
                    gzwrite($fh, ",\n");
                }
                $vals = implode(', ', array_map(fn ($c) => $q($r->{$c} ?? null), $cols));
                gzwrite($fh, "({$vals})");
                $rowInStmt++;
                $written++;
                if ($rowInStmt >= $perStmt) {
                    $closeStmt();
                }
            });
        $closeFile();

        $fin = sprintf('%s/autowini-%02dof%02d-finalize.sql', $work, $files + 1, $files);
        file_put_contents($fin,
            "-- Run LAST: rebuild stock_code from new ids.\n"
            . "UPDATE `products` SET `stock_code`=CONCAT('AW', id) "
            . "WHERE `website`='autowini' AND (`stock_code` IS NULL OR `stock_code`='');\n");

        file_put_contents($work . '/README.txt',
            "Supreme Motors — autowini inventory (" . number_format($n) . " cars)\n"
            . str_repeat('=', 52) . "\n\n"
            . "Files are gzipped SQL (.sql.gz). phpMyAdmin's Import tab reads .gz\n"
            . "directly (small upload, decompressed server-side); or via CLI:\n"
            . "    zcat autowini-01of{$files}.sql.gz | mysql -u USER -p DBNAME\n\n"
            . "Import IN ORDER. Each file is its own transaction, safe one at a time.\n"
            . "Chunk 1 clears old autowini rows; the finalize file (run LAST) rebuilds\n"
            . "stock codes. This ONLY touches website='autowini' rows — goo-net and\n"
            . "other sources are never affected.\n\n"
            . "After importing, clear caches on live:  php artisan cache:clear\n");

        $zip = $dir . '/autowini-products-chunks.zip';
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ((array) glob($work . '/*') as $f) {
            $z->addFile($f, basename($f));
        }
        $z->close();
        $this->info('EXPORTED ' . number_format($written) . " rows -> {$zip} "
            . '(' . round(filesize($zip) / 1024 / 1024, 1) . " MB, {$files} chunks + finalize)");

        return self::SUCCESS;
    }
}
