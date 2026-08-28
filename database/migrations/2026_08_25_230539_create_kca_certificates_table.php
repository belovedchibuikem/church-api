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
        Schema::create('kca_certificates', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_enrollment_id')->unique()->constrained('kca_enrollments')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('certificate_number', 100)->unique();
            $table->date('completion_on');
            $table->timestamp('issued_at');
            $table->string('digital_signature_reference', 191)->nullable();
            $table->char('verification_code_hash', 64)->unique();
            $table->char('issuance_key_hash', 64)->unique();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['person_id', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_certificates');
    }
};
