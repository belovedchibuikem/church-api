<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kca_governance_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('pass_threshold_percent')->default(70);
            $table->unsignedTinyInteger('attendance_threshold_percent')->default(75);
            $table->boolean('require_final_assessment')->default(true);
            $table->boolean('require_signed_pdf')->default(false);
            $table->string('certificate_signer_name', 120)->nullable();
            $table->string('certificate_signer_title', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('configuration_revision')->default(1);
            $table->timestamps();
        });

        Schema::create('kca_certificate_revocations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_certificate_id')->unique()->constrained('kca_certificates')->restrictOnDelete();
            $table->string('reason_code', 100);
            $table->string('notes', 500)->nullable();
            $table->foreignId('revoked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kca_certificate_revocations');
        Schema::dropIfExists('kca_governance_configurations');
    }
};
