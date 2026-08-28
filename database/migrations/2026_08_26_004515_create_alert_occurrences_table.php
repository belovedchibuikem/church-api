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
        Schema::create('alert_occurrences', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('alert_rule_id')->constrained()->restrictOnDelete();
            $table->string('condition_reference_type', 100);
            $table->string('condition_reference_key', 191);
            $table->char('condition_fingerprint_hash', 64);
            $table->string('scope_type', 100)->nullable();
            $table->string('scope_key', 64)->nullable();
            $table->string('status', 20);
            $table->unsignedTinyInteger('active_marker')->nullable()->default(1);
            $table->text('summary')->nullable();
            $table->timestamp('opened_at');
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_reason_code', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['alert_rule_id', 'condition_fingerprint_hash', 'active_marker'],
                'alert_occurrence_unresolved_unique',
            );
            $table->index(['status', 'opened_at']);
            $table->index(['scope_type', 'scope_key', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_occurrences');
    }
};
