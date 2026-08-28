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
        Schema::create('kca_module_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_module_id')->constrained('kca_modules')->restrictOnDelete();
            $table->foreignId('prerequisite_module_id')->constrained('kca_modules')->restrictOnDelete();
            $table->string('requirement', 60);
            $table->timestamps();

            $table->unique(
                ['kca_module_id', 'prerequisite_module_id', 'requirement'],
                'kca_module_prerequisite_unique',
            );
            $table->index(
                ['prerequisite_module_id', 'requirement'],
                'kca_prerequisite_requirement_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_module_prerequisites');
    }
};
