<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_lessons', function (Blueprint $table): void {
            if (! Schema::hasColumn('kca_lessons', 'summary')) {
                $table->string('summary', 500)->nullable()->after('title');
            }
            if (! Schema::hasColumn('kca_lessons', 'body')) {
                $table->text('body')->nullable()->after('summary');
            }
            if (! Schema::hasColumn('kca_lessons', 'content_url')) {
                $table->string('content_url', 2048)->nullable()->after('body');
            }
            if (! Schema::hasColumn('kca_lessons', 'estimated_minutes')) {
                $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('content_url');
            }
        });

        if (! Schema::hasTable('kca_leadership_recommendations')) {
            Schema::create('kca_leadership_recommendations', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('kca_application_id')->constrained('kca_applications')->restrictOnDelete();
                $table->string('recommender_name', 191);
                $table->string('recommender_email', 191);
                $table->string('recommender_role', 191)->nullable();
                $table->string('recommender_phone', 50)->nullable();
                $table->string('token_hash', 64);
                $table->string('status', 40)->default('requested');
                $table->text('statement')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('token_hash');
                $table->index('kca_application_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kca_leadership_recommendations');
        Schema::table('kca_lessons', function (Blueprint $table): void {
            foreach (['estimated_minutes', 'content_url', 'body', 'summary'] as $column) {
                if (Schema::hasColumn('kca_lessons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
