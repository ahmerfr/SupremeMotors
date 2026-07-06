<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De-cluster the default inventory ordering.
 *
 * AutoTrader UK was scraped in PRICE-BAND order, so insertion order (and thus
 * created_at, the old default sort) put hundreds of identically-priced cars back
 * to back — the inventory grid showed row after row of the same price. A stable
 * per-row random `shuffle_key`, sorted ASC, mixes prices/makes/countries while
 * keeping pagination deterministic, and is served straight off its index.
 *
 * Composite (country|category_id, shuffle_key) indexes keep the two biggest
 * facets filesort-free under the new default sort, mirroring the existing
 * (…, created_at) composites.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add at the END of the table (NOT ->after(...)) so MySQL/MariaDB uses an
        // INSTANT add — no full rebuild of the 7GB table (a positioned add forces a
        // COPY that can crash a shared host).
        if (!Schema::hasColumn('products', 'shuffle_key')) {
            Schema::table('products', function (Blueprint $t) {
                $t->unsignedInteger('shuffle_key')->nullable();
            });
        }

        // backfill a well-distributed key for every existing row. CRC32(id+salt)
        // is a single deterministic pass (no RAND state) that scrambles the
        // price-banded id order into a uniform 0..2^32 spread — fast even on a
        // shared host, and reproducible.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE products SET shuffle_key = CRC32(CONCAT(id, 'sm')) WHERE shuffle_key IS NULL");
        }

        Schema::table('products', function (Blueprint $t) {
            $t->index('shuffle_key', 'products_shuffle_idx');
            $t->index(['country', 'shuffle_key'], 'products_country_shuffle_idx');
            $t->index(['category_id', 'shuffle_key'], 'products_cat_shuffle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropIndex('products_shuffle_idx');
            $t->dropIndex('products_country_shuffle_idx');
            $t->dropIndex('products_cat_shuffle_idx');
            $t->dropColumn('shuffle_key');
        });
    }
};
