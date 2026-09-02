<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_applications', function (Blueprint $table) {
            $table->json('orientation_progress')->nullable()->after('reviewed_at');
            $table->timestamp('orientation_completed_at')->nullable()->after('orientation_progress');
        });
    }

    public function down(): void
    {
        Schema::table('kca_applications', function (Blueprint $table) {
            $table->dropColumn(['orientation_progress', 'orientation_completed_at']);
        });
    }
};
