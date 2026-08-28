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
        Schema::table('churches', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('name');
            $table->index(['published_at', 'name']);
        });

        Schema::table('home_church_applications', function (Blueprint $table) {
            $table->char('public_idempotency_scope_hash', 64)->nullable()->unique();
            $table->char('public_payload_fingerprint', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('home_church_applications', function (Blueprint $table) {
            $table->dropUnique(['public_idempotency_scope_hash']);
            $table->dropColumn(['public_idempotency_scope_hash', 'public_payload_fingerprint']);
        });

        Schema::table('churches', function (Blueprint $table) {
            $table->dropIndex(['published_at', 'name']);
            $table->dropColumn('published_at');
        });
    }
};
