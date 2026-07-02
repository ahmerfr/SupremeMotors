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
            'transmission', 'condition', 'color', 'steering', 'seats', 'drive_type',
        ];
        $filled = array_fill_keys($fields, 0);
        $processed = 0;

        DB::table('products')
            ->select('id', 'product_details')
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function ($rows) use ($fields, &$filled, &$processed) {
                DB::transaction(function () use ($rows, $fields, &$filled) {
                    foreach ($rows as $row) {
                        $attrs = ProductDetailsParser::parse($row->product_details);
                        foreach ($fields as $f) {
                            if ($attrs[$f] !== null) {
                                $filled[$f]++;
                            }
                        }
                        if (array_filter($attrs, fn ($v) => $v !== null) === []) {
                            continue;
                        }
                        DB::table('products')->where('id', $row->id)->update($attrs);
                    }
                });
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
