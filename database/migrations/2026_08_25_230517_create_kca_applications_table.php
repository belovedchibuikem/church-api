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
        Schema::create('kca_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('status', 40)->default('received');
            $table->timestamp('received_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'status']);
            $table->index(['status', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_applications');
    }
};
