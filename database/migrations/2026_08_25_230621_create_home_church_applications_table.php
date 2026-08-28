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
        Schema::create('home_church_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('applicant_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_church_id')
                ->nullable()
                ->unique()
                ->constrained('home_churches')
                ->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('administrative_unit_id')
                ->constrained('administrative_units')
                ->restrictOnDelete();
            $table->string('proposed_name', 191);
            $table->unsignedSmallInteger('expected_participants');
            $table->string('meeting_day', 16);
            $table->time('meeting_time');
            $table->text('contact_email');
            $table->text('contact_phone');
            $table->timestamp('guidelines_agreed_at');
            $table->string('status', 32)->default('draft');
            $table->unsignedTinyInteger('active_marker')->nullable()->default(1);
            $table->timestamp('status_changed_at');
            $table->timestamps();

            $table->unique(
                ['applicant_person_id', 'church_id', 'active_marker'],
                'home_church_application_active_unique',
            );
            $table->index(['church_id', 'status']);
            $table->index(['applicant_person_id', 'status']);
            $table->index(['administrative_unit_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_church_applications');
    }
};
