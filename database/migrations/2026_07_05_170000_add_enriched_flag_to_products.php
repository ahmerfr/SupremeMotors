<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-2 enrich needs a reliable, INDEXED "has this car had its full detail
 * fetched?" flag. It used `specifications IS NULL`, but the search tier already
 * writes a (search-shaped) specifications JSON, so that marker was always false
 * and enrich skipped every row. A dedicated `enriched` tinyint (0 = search-tier
 * only, 1 = full detail merged) fixes correctness AND lets the enrich cursor be
 * an index range scan instead of a full-table JSON scan on 460k rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'enriched')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('enriched')->default(false);
            });
        }

        // composite index the enrich cursor uses: WHERE website=? AND enriched=0
        DB::statement('CREATE INDEX IF NOT EXISTS products_website_enriched_idx ON products (website, enriched)');

        // any autotraderuk row already carrying a detail-sourced spec sheet is
        // enriched (MySQL only — the JSON fns aren't in the SQLite test driver,
        // whose DB starts empty anyway; scoped to the one website that uses it)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE products SET enriched = 1 WHERE website = 'autotraderuk' AND JSON_UNQUOTE(JSON_EXTRACT(specifications, '$.source')) = 'detail'");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_website_enriched_idx ON products');
        if (Schema::hasColumn('products', 'enriched')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('enriched');
            });
        }
    }
};
