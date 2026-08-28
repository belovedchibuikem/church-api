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
        Schema::create('scope_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('role_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('scope_type', 100);
            $table->string('scope_key', 64);
            $table->timestamps();

            $table->unique(
                ['role_assignment_id', 'scope_type', 'scope_key'],
                'scope_assignments_role_scope_unique',
            );
            $table->index(['scope_type', 'scope_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scope_assignments');
    }
};
