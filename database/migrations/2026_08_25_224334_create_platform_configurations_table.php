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
        Schema::create('platform_configurations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('key', 191);
            $table->string('value_type', 20);
            $table->string('classification', 20);
            $table->string('environment', 50);
            $table->string('scope_type', 100)->nullable();
            $table->string('scope_key', 64)->nullable();
            $table->char('context_hash', 64);
            $table->longText('stored_value');
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['key', 'context_hash']);
            $table->index(['environment', 'scope_type', 'scope_key']);
            $table->index('classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_configurations');
    }
};
