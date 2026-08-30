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
        Schema::create('church_groups', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained('churches')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('leader_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('church_id');
        });

        Schema::create('church_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_group_id')->constrained('church_groups')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['church_group_id', 'person_id']);
        });

        Schema::create('church_announcements', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained('churches')->restrictOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->index(['church_id', 'published_at']);
        });

        Schema::create('church_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained('churches')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('file_asset_id')->nullable()->constrained('file_assets')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kca_follows', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('follower_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('followed_person_id')->constrained('people')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['follower_person_id', 'followed_person_id']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('ticket_code', 64)->nullable()->unique()->after('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique(['ticket_code']);
            $table->dropColumn('ticket_code');
        });

        Schema::dropIfExists('kca_follows');
        Schema::dropIfExists('church_documents');
        Schema::dropIfExists('church_announcements');
        Schema::dropIfExists('church_group_memberships');
        Schema::dropIfExists('church_groups');
    }
};
