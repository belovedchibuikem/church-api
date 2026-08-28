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
        Schema::create('press_publications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('publisher_name');
            $table->string('edition', 100)->nullable();
            $table->date('publication_date')->nullable();
            $table->unsignedSmallInteger('copyright_year')->nullable();
            $table->string('language_code', 35);
            $table->unsignedInteger('page_count')->nullable();
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('cover_file_asset_id')->nullable()->constrained('file_assets')->restrictOnDelete();
            $table->foreignId('content_file_asset_id')->nullable()->constrained('file_assets')->restrictOnDelete();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('format', 32);
            $table->string('availability', 32)->default('unavailable');
            $table->string('status', 64)->default('manuscript');
            $table->string('isbn', 17)->nullable()->unique();
            $table->string('isbn_type', 10)->nullable();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamp('status_changed_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'availability', 'publication_date']);
            $table->index(['language_code', 'category']);
            $table->index(['format', 'availability']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('press_publications');
    }
};
