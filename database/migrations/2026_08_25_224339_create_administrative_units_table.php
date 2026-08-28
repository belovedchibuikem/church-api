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
        Schema::create('administrative_units', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('administrative_level_id')
                ->constrained('administrative_levels')
                ->restrictOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('administrative_units')
                ->restrictOnDelete();
            $table->string('name', 191);
            $table->string('reference_code', 100)->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'reference_code']);
            $table->index(['country_id', 'administrative_level_id']);
            $table->index(['country_id', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrative_units');
    }
};
