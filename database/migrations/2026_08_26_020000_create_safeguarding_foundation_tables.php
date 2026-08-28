<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->unique()->constrained()->restrictOnDelete();
            $table->text('date_of_birth')->nullable();
            $table->string('minor_status', 32)->index();
            $table->boolean('direct_communication_restricted')->default(true);
            $table->boolean('media_use_restricted')->default(true);
            $table->timestamps();
        });

        Schema::create('guardian_relationships', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('guardian_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('child_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('relationship_type', 50);
            $table->string('status', 32)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['child_person_id', 'status']);
            $table->index(['guardian_person_id', 'status']);
        });

        Schema::create('guardian_consents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('guardian_relationship_id')->constrained()->restrictOnDelete();
            $table->foreignId('evidence_file_asset_id')->nullable()->constrained('file_assets')->restrictOnDelete();
            $table->string('purpose', 100);
            $table->string('policy_version', 100);
            $table->string('source', 100);
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['guardian_relationship_id', 'purpose', 'withdrawn_at'], 'guardian_consents_current_index');
        });

        Schema::create('safeguarding_incidents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('subject_person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_code', 64)->unique();
            $table->string('concern_type', 100);
            $table->string('severity', 32)->index();
            $table->string('status', 32)->index();
            $table->text('restricted_summary');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_incidents');
        Schema::dropIfExists('guardian_consents');
        Schema::dropIfExists('guardian_relationships');
        Schema::dropIfExists('child_profiles');
    }
};
