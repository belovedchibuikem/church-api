<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_publications', function (Blueprint $table) {
            if (! Schema::hasColumn('press_publications', 'publication_type')) {
                $table->string('publication_type', 32)->default('book')->after('title');
            }
            if (! Schema::hasColumn('press_publications', 'slug')) {
                $table->string('slug', 191)->nullable()->unique()->after('publication_type');
            }
            if (! Schema::hasColumn('press_publications', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (! Schema::hasColumn('press_publications', 'type_metadata')) {
                $table->json('type_metadata')->nullable();
            }
            if (! Schema::hasColumn('press_publications', 'visibility')) {
                $table->string('visibility', 16)->default('public');
            }
            if (! Schema::hasColumn('press_publications', 'featured')) {
                $table->boolean('featured')->default(false);
            }
            if (! Schema::hasColumn('press_publications', 'scheduled_publish_at')) {
                $table->timestamp('scheduled_publish_at')->nullable();
            }
            if (! Schema::hasColumn('press_publications', 'scheduled_unpublish_at')) {
                $table->timestamp('scheduled_unpublish_at')->nullable();
            }
            if (! Schema::hasColumn('press_publications', 'scheduled_by_user_id')) {
                $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('press_publications', 'unpublished_at')) {
                $table->timestamp('unpublished_at')->nullable();
            }
            if (! Schema::hasColumn('press_publications', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }
            if (! Schema::hasColumn('press_publications', 'archive_reason_code')) {
                $table->string('archive_reason_code', 100)->nullable();
            }
        });

        if (Schema::hasColumn('press_publication_contributors', 'role') && ! Schema::hasColumn('press_publication_contributors', 'sort_order')) {
            Schema::table('press_publication_contributors', function (Blueprint $table) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('role');
            });
        }

        if (! Schema::hasTable('press_publication_assets')) {
            Schema::create('press_publication_assets', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('press_publication_id')->constrained()->cascadeOnDelete();
                $table->foreignId('file_asset_id')->constrained('file_assets')->restrictOnDelete();
                $table->string('asset_format', 32);
                $table->string('language_code', 35)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_current')->default(true);
                $table->boolean('is_required')->default(false);
                $table->string('processing_status', 32)->default('ready');
                $table->string('label', 191)->nullable();
                $table->char('checksum', 64)->nullable();
                $table->timestamps();
                $table->index(['press_publication_id', 'is_current'], 'press_assets_current_idx');
            });
        } else {
            Schema::table('press_publication_assets', function (Blueprint $table) {
                $sm = Schema::getConnection()->getSchemaBuilder();
                $indexes = $sm->getIndexes('press_publication_assets');
                $names = array_map(fn (array $index): string => $index['name'], $indexes);
                if (! in_array('press_assets_current_idx', $names, true)) {
                    $table->index(['press_publication_id', 'is_current'], 'press_assets_current_idx');
                }
            });
        }

        if (! Schema::hasTable('press_publication_reviews')) {
            Schema::create('press_publication_reviews', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('press_publication_id')->constrained()->cascadeOnDelete();
                $table->foreignId('reviewer_person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('stage', 32);
                $table->string('decision', 32);
                $table->json('checklist')->nullable();
                $table->text('comments')->nullable();
                $table->text('requested_changes')->nullable();
                $table->boolean('comments_public')->default(false);
                $table->timestamp('decided_at');
                $table->timestamps();
                $table->index(['press_publication_id', 'stage'], 'press_reviews_stage_idx');
            });
        }

        if (! Schema::hasTable('press_authors')) {
            Schema::create('press_authors', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();
                $table->string('display_name');
                $table->text('bio')->nullable();
                $table->foreignId('photo_file_asset_id')->nullable()->constrained('file_assets')->nullOnDelete();
                $table->string('status', 32)->default('active');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('press_authors');
        Schema::dropIfExists('press_publication_reviews');
        Schema::dropIfExists('press_publication_assets');
    }
};
