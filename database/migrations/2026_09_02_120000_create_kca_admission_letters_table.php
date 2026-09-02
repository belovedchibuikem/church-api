<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kca_admission_letters', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_application_id')->unique()->constrained('kca_applications')->restrictOnDelete();
            $table->string('reference_code', 64)->unique();
            $table->string('batch_label', 160)->nullable();
            $table->text('letter_body')->nullable();
            $table->string('signer_name', 120)->nullable();
            $table->string('signer_title', 120)->nullable();
            $table->foreignId('letterhead_file_asset_id')->nullable()->constrained('file_assets')->nullOnDelete();
            $table->foreignId('signature_file_asset_id')->nullable()->constrained('file_assets')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kca_admission_letters');
    }
};
