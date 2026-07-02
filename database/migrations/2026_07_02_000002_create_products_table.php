<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->char('mongo_id', 24)->nullable()->unique();
            $table->string('title', 500);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('make_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('country', 100)->nullable();
            $table->string('website', 100)->nullable();
            $table->string('body_style', 100)->nullable();
            $table->text('product_link')->nullable();
            $table->string('front_image', 500)->nullable();
            $table->json('other_images')->nullable();
            $table->mediumText('product_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
