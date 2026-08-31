<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crusades', function (Blueprint $table): void {
            if (! Schema::hasColumn('crusades', 'code')) {
                $table->string('code', 50)->nullable()->after('name');
            }
            if (! Schema::hasColumn('crusades', 'theme')) {
                $table->string('theme', 191)->nullable()->after('code');
            }
            if (! Schema::hasColumn('crusades', 'purpose')) {
                $table->string('purpose', 500)->nullable()->after('theme');
            }
            if (! Schema::hasColumn('crusades', 'description')) {
                $table->text('description')->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('crusades', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('description');
            }
            if (! Schema::hasColumn('crusades', 'status')) {
                $table->string('status', 40)->default('draft')->after('timezone');
            }
            if (! Schema::hasColumn('crusades', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('crusades', 'archive_reason_code')) {
                $table->string('archive_reason_code', 100)->nullable()->after('archived_at');
            }
        });

        Schema::table('mission_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('mission_invitations', 'purpose')) {
                $table->string('purpose', 500)->nullable()->after('requested_location_id');
            }
            if (! Schema::hasColumn('mission_invitations', 'expected_attendance')) {
                $table->unsignedInteger('expected_attendance')->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('mission_invitations', 'notes')) {
                $table->text('notes')->nullable()->after('expected_attendance');
            }
            if (! Schema::hasColumn('mission_invitations', 'application_data')) {
                $table->json('application_data')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('mission_invitations', 'idempotency_key_hash')) {
                $table->char('idempotency_key_hash', 64)->nullable()->unique()->after('application_data');
            }
        });

        Schema::table('mission_soul_journeys', function (Blueprint $table): void {
            if (! Schema::hasColumn('mission_soul_journeys', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('captured_at');
            }
            if (! Schema::hasColumn('mission_soul_journeys', 'conversion_reason_code')) {
                $table->string('conversion_reason_code', 100)->nullable()->after('converted_at');
            }
        });

        if (! Schema::hasTable('mission_partners')) {
            Schema::create('mission_partners', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('name', 191);
                $table->string('partner_type', 40)->default('organisation');
                $table->string('status', 40)->default('active');
                $table->string('geography', 191)->nullable();
                $table->string('contact_name', 191)->nullable();
                $table->string('contact_email', 191)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mission_support_requests')) {
            Schema::create('mission_support_requests', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('requested_by_person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->foreignId('crusade_id')->nullable()->constrained('crusades')->nullOnDelete();
                $table->string('title', 191);
                $table->string('category', 80)->default('general');
                $table->string('priority', 40)->default('normal');
                $table->string('status', 40)->default('submitted');
                $table->unsignedInteger('amount_minor')->nullable();
                $table->string('currency', 8)->nullable();
                $table->text('details')->nullable();
                $table->string('idempotency_key_hash', 64)->nullable()->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_support_requests');
        Schema::dropIfExists('mission_partners');
    }
};
