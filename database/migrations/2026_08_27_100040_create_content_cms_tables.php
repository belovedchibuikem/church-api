<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('slug', 100)->unique();
            $table->string('title', 191);
            $table->string('summary', 500)->nullable();
            $table->text('body');
            $table->string('locale', 35)->default('en');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'slug']);
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('page_id')->constrained('content_pages')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('title', 191);
            $table->text('body');
            $table->json('meta')->nullable();
            $table->string('href', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
            $table->index(['page_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_pages');
    }
};
