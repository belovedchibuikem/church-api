<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('subject', 191);
            $table->text('body');
            $table->string('status', 32)->default('open');
            $table->timestamps();

            $table->index(['person_id', 'created_at']);
            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_requests');
    }
};
