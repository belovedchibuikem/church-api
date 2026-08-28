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
        Schema::create('church_memberships', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_church_id')
                ->nullable()
                ->constrained('home_churches')
                ->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->unsignedTinyInteger('active_marker')->nullable()->default(1);
            $table->timestamp('joined_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason_code', 100)->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'church_id', 'active_marker']);
            $table->index(['church_id', 'status']);
            $table->index(['home_church_id', 'status']);
            $table->index(['person_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('church_memberships');
    }
};
