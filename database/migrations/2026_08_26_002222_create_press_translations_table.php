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
        Schema::create('press_translations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('press_publication_id')->constrained()->cascadeOnDelete();
            $table->string('target_language_code', 35);
            $table->string('translated_title');
            $table->string('translated_subtitle')->nullable();
            $table->text('translated_description')->nullable();
            $table->longText('translated_content')->nullable();
            $table->string('status', 32)->default('machine_generated');
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamp('status_changed_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['press_publication_id', 'target_language_code'], 'press_translation_language_unique');
            $table->index(['status', 'target_language_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('press_translations');
    }
};
