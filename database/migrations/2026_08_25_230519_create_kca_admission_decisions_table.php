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
        Schema::create('kca_admission_decisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_application_id')->unique()->constrained('kca_applications')->restrictOnDelete();
            $table->string('outcome', 40);
            $table->string('reason_code', 100)->nullable();
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['outcome', 'decided_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_admission_decisions');
    }
};
