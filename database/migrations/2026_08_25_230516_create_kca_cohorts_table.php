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
        Schema::create('kca_cohorts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_year_id')->constrained('kca_years')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['kca_year_id', 'code']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_cohorts');
    }
};
