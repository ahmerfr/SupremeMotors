<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queryform_entry', function (Blueprint $table) {
            $table->id();
            $table->string('company')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->index();
            $table->string('phone', 50)->nullable();
            $table->string('meeting', 20)->nullable();
            $table->string('visit', 20)->nullable();
            $table->integer('closing')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queryform_entry');
    }
};
