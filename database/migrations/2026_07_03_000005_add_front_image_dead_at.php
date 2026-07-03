<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Set by products:verify-images when the scraped image URL 404s
            // (vehicle delisted at the source). Homepage queries skip these.
            $table->dateTime('front_image_dead_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('front_image_dead_at');
        });
    }
};
