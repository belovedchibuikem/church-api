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
        Schema::create('ministry_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('category_code', 100);
            $table->string('name', 191);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->unsignedBigInteger('fee_amount_minor')->nullable();
            $table->char('fee_currency', 3)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();

            $table->index(['category_code', 'starts_at']);
            $table->index(
                ['registration_opens_at', 'registration_closes_at'],
                'ministry_event_registration_window_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministry_events');
    }
};
