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
        Schema::create('home_churches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('leader_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('administrative_unit_id')
                ->constrained('administrative_units')
                ->restrictOnDelete();
            $table->string('name', 191);
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['location_id', 'name']);
            $table->index(['church_id', 'status']);
            $table->index(['leader_person_id', 'status']);
            $table->index(['administrative_unit_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_churches');
    }
};
