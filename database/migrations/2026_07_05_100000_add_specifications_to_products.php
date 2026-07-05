<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single JSON column to hold the full structured specification set a source
 * provides (engine, power, torque, fuel economy, CO2, tyres, equipment
 * flags, ...). Sources like AutoTrader expose 30-40 spec fields per car;
 * rather than sprout a column per field, we keep them all queryable here and
 * still lift the few filterable scalars (power_hp, etc.) into real columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'specifications')) {
                $table->json('specifications')->nullable()->after('product_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'specifications')) {
                $table->dropColumn('specifications');
            }
        });
    }
};
