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
        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('administrative_unit_id')
                ->constrained('administrative_units')
                ->restrictOnDelete();
            $table->string('name', 191);
            $table->timestamps();

            $table->unique(['location_id', 'name']);
            $table->index(['administrative_unit_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('churches');
    }
};
