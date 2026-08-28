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
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->string('scope_type', 100)->nullable();
            $table->string('scope_key', 64)->nullable();
            $table->text('data_categories')->nullable();
            $table->timestamp('export_expires_at')->nullable();

            $table->index(['scope_type', 'scope_key']);
            $table->index(['status', 'export_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->dropIndex(['scope_type', 'scope_key']);
            $table->dropIndex(['status', 'export_expires_at']);
            $table->dropColumn(['scope_type', 'scope_key', 'data_categories', 'export_expires_at']);
        });
    }
};
