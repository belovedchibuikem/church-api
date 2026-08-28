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
        Schema::create('communication_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('communication_template_id')->constrained('communication_templates')->restrictOnDelete();
            $table->foreignId('communication_audience_id')->constrained('communication_audiences')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('channel', 20);
            $table->string('purpose', 100);
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(
                ['communication_audience_id', 'created_at'],
                'communication_broadcast_audience_created_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_broadcasts');
    }
};
