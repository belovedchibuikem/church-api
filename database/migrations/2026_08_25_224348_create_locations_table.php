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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('administrative_unit_id')
                ->nullable()
                ->constrained('administrative_units')
                ->restrictOnDelete();
            $table->string('name', 191);
            $table->string('address_line_one', 191)->nullable();
            $table->string('address_line_two', 191)->nullable();
            $table->string('locality', 191)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('timezone', 64);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['country_id', 'administrative_unit_id']);
            $table->index('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
