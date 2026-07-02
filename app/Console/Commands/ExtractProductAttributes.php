<?php

namespace App\Console\Commands;

use App\Support\ProductDetailsParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtractProductAttributes extends Command
{
    protected $signature = 'products:extract-attributes {--chunk=1000}';

    protected $description = 'Parse product_details HTML into structured attribute columns and assign SM stock codes';

    public function handle(): int
    {
        // Stock codes: one set-based statement, no parsing needed.
        DB::statement("UPDATE products SET stock_code = CONCAT('SM', id) WHERE stock_code IS NULL OR stock_code != CONCAT('SM', id)");
        $this->info('stock_code assigned.');

        $fields = [
            'model', 'model_code', 'year', 'engine_cc', 'mileage_km', 'fuel',
            'transmission', 'condition', 'color', 'steering', 'seats', 'doors', 'drive_type',
        ];
        $filled = array_fill_keys($fields, 0);
        $processed = 0;

        DB::table('products')
            ->select('id', 'title', 'product_details')
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function ($rows) use ($fields, &$filled, &$processed) {
                // One multi-row upsert per chunk: every id already exists, so
                // this is pure batched UPDATE — per-row UPDATEs run ~17 rows/s
                // against this table's 9 secondary indexes.
                $updates = [];
                foreach ($rows as $row) {
                    $attrs = ProductDetailsParser::parse($row->product_details);
                    foreach ($fields as $f) {
                        if ($attrs[$f] !== null) {
                            $filled[$f]++;
                        }
                    }
                    // Always include the row — skipping all-null parses would
                    // leave stale values from earlier runs in place.
                    // title: strict mode validates NOT NULL columns on the
                    // INSERT clause even though every id hits the UPDATE path.
                    $updates[] = array_merge(['id' => $row->id, 'title' => $row->title], $attrs);
                }
                if ($updates !== []) {
                    DB::table('products')->upsert($updates, ['id'], $fields);
                }
                $processed += count($rows);
                if ($processed % 20000 === 0) {
                    $this->info("processed {$processed}...");
                }
            });

        $this->info("Done. Processed {$processed} products. Fill counts:");
        foreach ($filled as $f => $c) {
            $this->line(sprintf('  %-14s %d', $f, $c));
        }

        return self::SUCCESS;
    }
}
