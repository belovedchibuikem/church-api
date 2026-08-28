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
        Schema::create('file_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('owner_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('purpose', 100);
            $table->string('classification', 32);
            $table->string('storage_provider', 32);
            $table->string('disk_name', 64);
            $table->unsignedInteger('storage_configuration_revision')->nullable();
            $table->string('object_key', 512)->unique();
            $table->json('metadata')->nullable();
            $table->string('detected_mime_type', 127);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->char('idempotency_key_hash', 64);
            $table->char('idempotency_scope_hash', 64)->unique();
            $table->string('status', 32)->default('quarantined');
            $table->string('malware_scan_status', 32)->default('pending');
            $table->timestamp('malware_scanned_at')->nullable();
            $table->string('rejection_reason', 64)->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['owner_person_id', 'purpose', 'created_at']);
            $table->index(['classification', 'status']);
            $table->index(['storage_provider', 'disk_name']);
            $table->index('sha256');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_assets');
    }
};
