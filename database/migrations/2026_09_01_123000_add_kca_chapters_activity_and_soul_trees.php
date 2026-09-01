<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kca_chapters')) {
            Schema::create('kca_chapters', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('kca_lesson_id')->constrained('kca_lessons')->restrictOnDelete();
                $table->string('code', 50);
                $table->string('title', 191);
                $table->string('summary', 500)->nullable();
                $table->text('body')->nullable();
                $table->string('content_url', 2048)->nullable();
                $table->unsignedSmallInteger('estimated_minutes')->nullable();
                $table->unsignedSmallInteger('sequence');
                $table->timestamps();

                $table->unique(['kca_lesson_id', 'code']);
                $table->unique(['kca_lesson_id', 'sequence']);
            });
        }

        if (! Schema::hasTable('kca_chapter_progress')) {
            Schema::create('kca_chapter_progress', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
                $table->foreignId('kca_chapter_id')->constrained('kca_chapters')->restrictOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('idempotency_key', 80)->nullable();
                $table->timestamps();

                $table->unique(['kca_enrollment_id', 'kca_chapter_id']);
                $table->unique(['kca_enrollment_id', 'idempotency_key']);
            });
        }

        if (! Schema::hasTable('kca_study_notes')) {
            Schema::create('kca_study_notes', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
                $table->foreignId('kca_lesson_id')->nullable()->constrained('kca_lessons')->nullOnDelete();
                $table->foreignId('kca_chapter_id')->nullable()->constrained('kca_chapters')->nullOnDelete();
                $table->string('title', 191)->nullable();
                $table->text('body');
                $table->timestamps();

                $table->index(['kca_enrollment_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('kca_devotional_readings')) {
            Schema::create('kca_devotional_readings', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
                $table->string('title', 191);
                $table->string('source', 191)->nullable();
                $table->string('publication_id', 26)->nullable();
                $table->text('reflection')->nullable();
                $table->timestamp('read_at');
                $table->timestamps();

                $table->index(['kca_enrollment_id', 'read_at']);
            });
        }

        if (! Schema::hasTable('kca_soul_wins')) {
            Schema::create('kca_soul_wins', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('kca_assignment_id')->constrained('kca_assignments')->restrictOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('kca_soul_wins')->restrictOnDelete();
                $table->unsignedSmallInteger('depth');
                $table->string('given_name', 191);
                $table->string('family_name', 191)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 191)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('won_at');
                $table->timestamps();

                $table->index(['kca_assignment_id', 'parent_id']);
                $table->index(['kca_assignment_id', 'depth']);
            });
        }

        Schema::table('kca_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('kca_assignments', 'assignment_kind')) {
                $table->string('assignment_kind', 40)->default('standard')->after('title');
            }
            if (! Schema::hasColumn('kca_assignments', 'soul_tree_spec')) {
                $table->json('soul_tree_spec')->nullable()->after('assignment_kind');
            }
        });

        $this->backfillChaptersFromLessons();
    }

    public function down(): void
    {
        Schema::dropIfExists('kca_soul_wins');
        Schema::dropIfExists('kca_devotional_readings');
        Schema::dropIfExists('kca_study_notes');
        Schema::dropIfExists('kca_chapter_progress');
        Schema::dropIfExists('kca_chapters');
        Schema::table('kca_assignments', function (Blueprint $table): void {
            foreach (['soul_tree_spec', 'assignment_kind'] as $column) {
                if (Schema::hasColumn('kca_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillChaptersFromLessons(): void
    {
        if (! Schema::hasTable('kca_lessons') || ! Schema::hasTable('kca_chapters')) {
            return;
        }

        $lessons = DB::table('kca_lessons')->orderBy('id')->get();
        $now = now();
        foreach ($lessons as $lesson) {
            $exists = DB::table('kca_chapters')->where('kca_lesson_id', $lesson->id)->exists();
            if ($exists) {
                continue;
            }
            $body = isset($lesson->body) ? (string) $lesson->body : '';
            $summary = isset($lesson->summary) ? (string) $lesson->summary : '';
            $contentUrl = isset($lesson->content_url) ? (string) $lesson->content_url : '';
            if ($body === '' && $summary === '' && $contentUrl === '') {
                continue;
            }
            DB::table('kca_chapters')->insert([
                'public_id' => (string) Str::ulid(),
                'kca_lesson_id' => $lesson->id,
                'code' => 'CH01',
                'title' => 'Chapter 1',
                'summary' => $summary !== '' ? mb_substr($summary, 0, 500) : null,
                'body' => $body !== '' ? $body : null,
                'content_url' => $contentUrl !== '' ? $contentUrl : null,
                'estimated_minutes' => $lesson->estimated_minutes ?? null,
                'sequence' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
