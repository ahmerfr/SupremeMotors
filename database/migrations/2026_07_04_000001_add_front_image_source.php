<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Original scraped URLs, kept when front_image/other_images are
            // swapped to our Bunny CDN copies by products:mirror-images.
            $table->string('front_image_source', 500)->nullable();
            $table->longText('other_images_source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['front_image_source', 'other_images_source']);
        });
    }
};
