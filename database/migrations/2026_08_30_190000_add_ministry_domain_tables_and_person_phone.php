<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person_profiles', function (Blueprint $table): void {
            $table->text('phone')->nullable()->after('preferred_name');
        });

        Schema::create('converts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_church_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at');
            $table->timestamp('baptized_at')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('status', 40)->default('active');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['person_id', 'church_id']);
            $table->index(['church_id', 'converted_at']);
        });

        Schema::create('evangelism_activities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('title', 191);
            $table->string('activity_type', 80)->default('outreach');
            $table->unsignedInteger('souls_reached')->default(0);
            $table->unsignedInteger('decisions')->default(0);
            $table->timestamp('occurred_at');
            $table->string('status', 40)->default('completed');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['church_id', 'occurred_at']);
        });

        Schema::create('church_departments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('description', 500)->nullable();
            $table->foreignId('leader_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('status', 40)->default('active');
            $table->timestamps();
            $table->unique(['church_id', 'name']);
        });

        Schema::create('church_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('church_departments')->nullOnDelete();
            $table->string('role_type', 40); // worker|leader|disciple
            $table->string('title', 191);
            $table->string('status', 40)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'role_type', 'status']);
        });

        Schema::create('counselling_cases', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('counselor_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('case_type', 100)->default('general');
            $table->string('status', 40)->default('open');
            $table->text('summary')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'status']);
        });

        Schema::create('testimonies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->string('title', 191);
            $table->text('body');
            $table->string('status', 40)->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('home_church_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('home_church_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->unsignedInteger('adults')->default(0);
            $table->unsignedInteger('children')->default(0);
            $table->unsignedInteger('first_timers')->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['home_church_id', 'service_date'], 'hc_attendance_church_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_church_attendance_records');
        Schema::dropIfExists('testimonies');
        Schema::dropIfExists('counselling_cases');
        Schema::dropIfExists('church_role_assignments');
        Schema::dropIfExists('church_departments');
        Schema::dropIfExists('evangelism_activities');
        Schema::dropIfExists('converts');
        Schema::table('person_profiles', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};
