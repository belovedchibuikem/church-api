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
        Schema::create('kca_lessons', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_module_id')->constrained('kca_modules')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('title', 191);
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['kca_module_id', 'code']);
            $table->unique(['kca_module_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_lessons');
    }
};
