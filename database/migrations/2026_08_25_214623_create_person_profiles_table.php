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
        Schema::create('person_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->restrictOnDelete();
            $table->string('given_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('family_name', 100);
            $table->string('preferred_name', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_profiles');
    }
};
