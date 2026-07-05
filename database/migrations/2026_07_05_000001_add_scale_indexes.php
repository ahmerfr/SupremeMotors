<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scale-proofing for the 1.5-2M product target: remaining sort columns get
 * indexes (year/mileage filesorts) and the common single-filter browses get
 * (filter, created_at) composites so filtered pages stay index-ordered at any
 * size. Idempotent — only creates indexes that are missing — so a partially
 * applied earlier run (which left year/mileage behind) can complete cleanly.
 */
return new class extends Migration
{
    /** index name => columns */
    private const INDEXES = [
        'products_year_idx' => ['year'],
        'products_mileage_idx' => ['mileage_km'],
        'products_cat_created_idx' => ['category_id', 'created_at'],
        'products_make_created_idx' => ['make_id', 'created_at'],
        'products_country_created_idx' => ['country', 'created_at'],
    ];

    public function up(): void
    {
        $existing = collect(Schema::getIndexes('products'))->pluck('name')->all();

        Schema::table('products', function (Blueprint $table) use ($existing) {
            foreach (self::INDEXES as $name => $columns) {
                if (! in_array($name, $existing, true)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        $existing = collect(Schema::getIndexes('products'))->pluck('name')->all();

        Schema::table('products', function (Blueprint $table) use ($existing) {
            foreach (array_keys(self::INDEXES) as $name) {
                if (in_array($name, $existing, true)) {
                    $table->dropIndex($name);
                }
            }
        });
    }
};
