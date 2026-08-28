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
        Schema::create('communication_audience_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('communication_audience_id')->constrained('communication_audiences')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('selector_key', 191)->nullable();
            $table->string('scope_type', 100)->nullable();
            $table->string('scope_key', 64)->nullable();
            $table->timestamps();

            $table->index(
                ['communication_audience_id', 'type'],
                'communication_audience_rule_type_index',
            );
            $table->index(['scope_type', 'scope_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_audience_rules');
    }
};
