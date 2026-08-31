<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_work_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title', 191);
            $table->text('body')->nullable();
            $table->string('status', 32);
            $table->string('priority', 32);
            $table->timestamp('due_at')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['assigned_to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_work_items');
    }
};
