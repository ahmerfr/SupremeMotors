<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // Single combined ALTER — one table rebuild (see 2026_07_02_200000).
            DB::statement('ALTER TABLE products
                ADD COLUMN doors TINYINT UNSIGNED NULL AFTER seats,
                ADD INDEX products_doors_idx (doors)');

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->tinyInteger('doors')->unsigned()->nullable();
            $table->index('doors');
        });
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products DROP INDEX products_doors_idx, DROP COLUMN doors');

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['doors']);
            $table->dropColumn('doors');
        });
    }
};
