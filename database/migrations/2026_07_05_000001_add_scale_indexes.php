<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scale-proofing for the 1.5-2M product target: remaining sort columns get
 * indexes (year/mileage filesorts; price since 2026_07_02) and the common
 * single-filter browses get (filter, created_at) composites so filtered
 * pages stay index-ordered at any size. body_style composites exist since
 * 2026_07_03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('year', 'products_year_idx');
            $table->index('mileage_km', 'products_mileage_idx');
            $table->index(['category_id', 'created_at'], 'products_cat_created_idx');
            $table->index(['make_id', 'created_at'], 'products_make_created_idx');
            $table->index(['country', 'created_at'], 'products_country_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_year_idx');
            $table->dropIndex('products_mileage_idx');
            $table->dropIndex('products_cat_created_idx');
            $table->dropIndex('products_make_created_idx');
            $table->dropIndex('products_country_created_idx');
        });
    }
};
