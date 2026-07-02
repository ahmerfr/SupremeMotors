<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'make_id'], 'products_category_make_idx');
            $table->index(['category_id', 'country'], 'products_category_country_idx');
            $table->index('body_style', 'products_body_style_idx');
            $table->index('country', 'products_country_idx');
            $table->index(['website', 'created_at'], 'products_website_created_idx');
            $table->index('created_at', 'products_created_idx');
            $table->index('price', 'products_price_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'categories_type_created_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products ADD FULLTEXT products_search_ft (title, product_details)');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_make_idx');
            $table->dropIndex('products_category_country_idx');
            $table->dropIndex('products_body_style_idx');
            $table->dropIndex('products_country_idx');
            $table->dropIndex('products_website_created_idx');
            $table->dropIndex('products_created_idx');
            $table->dropIndex('products_price_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_type_created_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products DROP INDEX products_search_ft');
        }
    }
};
