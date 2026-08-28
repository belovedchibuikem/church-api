<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('export_file_asset_id')->nullable()->constrained('file_assets')->restrictOnDelete();
            $table->string('request_type', 32)->index();
            $table->string('status', 32)->index();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->text('request_notes')->nullable();
            $table->string('decision_reason_code', 100)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['person_id', 'request_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_subject_requests');
    }
};
