<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'stock_code', 'model', 'model_code', 'year', 'engine_cc', 'mileage_km',
        'fuel', 'transmission', 'condition', 'color', 'steering', 'seats', 'drive_type',
    ];

    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // One combined ALTER = one table rebuild. Column-by-column Blueprint
            // ALTERs each copy the 2 GB table (~3 min x 22 statements).
            $drops = collect(self::COLUMNS)
                ->filter(fn ($c) => Schema::hasColumn('products', $c))
                ->map(fn ($c) => "DROP COLUMN `{$c}`")
                ->implode(', ');
            if ($drops !== '') {
                DB::statement("ALTER TABLE products {$drops}");
            }

            DB::statement(<<<'SQL'
                ALTER TABLE products
                    ADD COLUMN stock_code   VARCHAR(20)       NULL AFTER mongo_id,
                    ADD COLUMN model        VARCHAR(100)      NULL AFTER title,
                    ADD COLUMN model_code   VARCHAR(60)       NULL AFTER model,
                    ADD COLUMN year         SMALLINT UNSIGNED NULL AFTER model_code,
                    ADD COLUMN engine_cc    INT UNSIGNED      NULL AFTER year,
                    ADD COLUMN mileage_km   INT UNSIGNED      NULL AFTER engine_cc,
                    ADD COLUMN fuel         VARCHAR(30)       NULL AFTER mileage_km,
                    ADD COLUMN transmission VARCHAR(30)       NULL AFTER fuel,
                    ADD COLUMN `condition`  VARCHAR(40)       NULL AFTER transmission,
                    ADD COLUMN color        VARCHAR(40)       NULL AFTER `condition`,
                    ADD COLUMN steering     VARCHAR(10)       NULL AFTER color,
                    ADD COLUMN seats        TINYINT UNSIGNED  NULL AFTER steering,
                    ADD COLUMN drive_type   VARCHAR(30)       NULL AFTER seats,
                    ADD UNIQUE products_stock_code_unique (stock_code),
                    ADD INDEX products_year_idx (year),
                    ADD INDEX products_mileage_idx (mileage_km),
                    ADD INDEX products_engine_idx (engine_cc),
                    ADD INDEX products_fuel_idx (fuel),
                    ADD INDEX products_transmission_idx (transmission),
                    ADD INDEX products_condition_idx (`condition`),
                    ADD INDEX products_steering_idx (steering),
                    ADD INDEX products_drive_type_idx (drive_type),
                    ADD INDEX products_make_year_idx (make_id, year)
                SQL);

            return;
        }

        // sqlite (tests): tiny tables, Blueprint is fine.
        Schema::table('products', function (Blueprint $table) {
            $table->string('stock_code', 20)->nullable()->unique();
            $table->string('model', 100)->nullable();
            $table->string('model_code', 60)->nullable();
            $table->smallInteger('year')->unsigned()->nullable();
            $table->integer('engine_cc')->unsigned()->nullable();
            $table->integer('mileage_km')->unsigned()->nullable();
            $table->string('fuel', 30)->nullable();
            $table->string('transmission', 30)->nullable();
            $table->string('condition', 40)->nullable();
            $table->string('color', 40)->nullable();
            $table->string('steering', 10)->nullable();
            $table->tinyInteger('seats')->unsigned()->nullable();
            $table->string('drive_type', 30)->nullable();
            $table->index('year');
            $table->index('mileage_km');
            $table->index('fuel');
        });
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $drops = collect(self::COLUMNS)
                ->filter(fn ($c) => Schema::hasColumn('products', $c))
                ->map(fn ($c) => "DROP COLUMN `{$c}`")
                ->implode(', ');
            if ($drops !== '') {
                DB::statement("ALTER TABLE products {$drops}");
            }

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
