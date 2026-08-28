<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('administrative_levels', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name', 191);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['country_id', 'code']);
            $table->unique(['country_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrative_levels');
    }
};
