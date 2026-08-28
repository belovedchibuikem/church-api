<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_request_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('prayer_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->index(['prayer_request_id', 'assigned_at'], 'prayer_assignment_request_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_request_assignments');
    }
};
