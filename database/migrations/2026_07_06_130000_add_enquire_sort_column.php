<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price sorts did an unavoidable filesort (~3s over 261k rows) because the
 * ORDER BY was a runtime expression `(price IS NULL OR price=0 OR website NOT
 * IN(...)) asc, price` — no index can serve a computed key.
 *
 * Add a VIRTUAL generated column `enquire_sort` (0 = show the numeric price,
 * 1 = "Enquire": no/zero price or a source whose prices we don't display) and
 * index (country|category_id, enquire_sort, price). `ORDER BY enquire_sort ASC,
 * price ASC` is then fully index-ordered -> LIMIT 30 short-circuits, no filesort.
 * VIRTUAL (not STORED) so it's a metadata change, not a 6GB table rewrite; the
 * index materialises the values. The site list matches Products::PRICE_VISIBLE_
 * SITES (which the old sort expression had drifted out of sync with — it was
 * missing autotraderuk/perfectmotors, wrongly sinking 454k priced UK cars).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasColumn('products', 'enquire_sort')) {
            DB::statement(
                "ALTER TABLE products ADD COLUMN enquire_sort TINYINT AS (
                    IF(price > 0 AND website IN ('tcv','suprememotors','electricvehicles','autotraderza','autotraderuk','perfectmotors'), 0, 1)
                ) VIRTUAL"
            );
        }
        DB::statement('CREATE INDEX IF NOT EXISTS products_country_enquire_price_idx ON products (country, enquire_sort, price)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_enquire_price_idx ON products (enquire_sort, price)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS products_country_enquire_price_idx ON products');
        DB::statement('DROP INDEX IF EXISTS products_enquire_price_idx ON products');
        if (Schema::hasColumn('products', 'enquire_sort')) {
            DB::statement('ALTER TABLE products DROP COLUMN enquire_sort');
        }
    }
};
