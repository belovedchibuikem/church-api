<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livestreams', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 191);
            $table->string('subtitle', 255)->nullable();
            $table->string('host_name', 120)->nullable();
            $table->string('provider', 32)->default('youtube');
            $table->string('external_id', 64);
            $table->string('watch_url', 512);
            $table->string('embed_url', 512);
            $table->string('status', 32)->default('scheduled');
            $table->unsignedInteger('viewer_count')->default(0);
            $table->unsignedInteger('reaction_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('livestream_comments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('livestream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamps();

            $table->index(['livestream_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livestream_comments');
        Schema::dropIfExists('livestreams');
    }
};
