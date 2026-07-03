<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Serves the per-make interleave on the homepage body-type
            // section: newest N per make within a body style.
            $table->index(['body_style', 'make_id', 'created_at'], 'products_body_make_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_body_make_created_idx');
        });
    }
};
