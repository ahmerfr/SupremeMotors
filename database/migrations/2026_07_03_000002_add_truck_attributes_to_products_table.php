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
                ADD COLUMN axles TINYINT UNSIGNED NULL AFTER drive_type,
                ADD COLUMN load_capacity_kg INT UNSIGNED NULL AFTER axles,
                ADD COLUMN power_hp SMALLINT UNSIGNED NULL AFTER load_capacity_kg,
                ADD COLUMN emission_standard VARCHAR(10) NULL AFTER power_hp,
                ADD COLUMN running_hours INT UNSIGNED NULL AFTER emission_standard,
                ADD INDEX products_axles_idx (axles),
                ADD INDEX products_load_capacity_idx (load_capacity_kg),
                ADD INDEX products_power_idx (power_hp),
                ADD INDEX products_emission_idx (emission_standard),
                ADD INDEX products_running_hours_idx (running_hours)');

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->tinyInteger('axles')->unsigned()->nullable();
            $table->integer('load_capacity_kg')->unsigned()->nullable();
            $table->smallInteger('power_hp')->unsigned()->nullable();
            $table->string('emission_standard', 10)->nullable();
            $table->integer('running_hours')->unsigned()->nullable();
            $table->index('power_hp');
            $table->index('load_capacity_kg');
        });
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products
                DROP INDEX products_axles_idx, DROP INDEX products_load_capacity_idx,
                DROP INDEX products_power_idx, DROP INDEX products_emission_idx,
                DROP INDEX products_running_hours_idx,
                DROP COLUMN axles, DROP COLUMN load_capacity_kg, DROP COLUMN power_hp,
                DROP COLUMN emission_standard, DROP COLUMN running_hours');

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['axles', 'load_capacity_kg', 'power_hp', 'emission_standard', 'running_hours']);
        });
    }
};
