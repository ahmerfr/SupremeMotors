<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performance indexes for the /inventory listing at 838k rows (perf audit).
 *
 * - (country, price) / (category_id, price): price-sorted filtered views did a
 *   261k-row filesort (~3-8s); a composite lets the filter+sort be index-served
 *   with a LIMIT short-circuit.
 * - front_image_dead_at: the listing hides dead-image rows; indexing it keeps
 *   any COUNT(... IS NULL) off a full-table scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite test DB doesn't need these
        }
        DB::statement('CREATE INDEX IF NOT EXISTS products_country_price_idx ON products (country, price)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_category_price_idx ON products (category_id, price)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_dead_idx ON products (front_image_dead_at)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS products_country_price_idx ON products');
        DB::statement('DROP INDEX IF EXISTS products_category_price_idx ON products');
        DB::statement('DROP INDEX IF EXISTS products_dead_idx ON products');
    }
};
