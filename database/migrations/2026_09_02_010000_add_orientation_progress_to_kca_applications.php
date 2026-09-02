<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kca_applications', 'orientation_progress')) {
            Schema::table('kca_applications', function (Blueprint $table): void {
                $table->json('orientation_progress')->nullable()->after('reviewed_at');
            });
        }

        if (! Schema::hasColumn('kca_applications', 'orientation_completed_at')) {
            Schema::table('kca_applications', function (Blueprint $table): void {
                $table->timestamp('orientation_completed_at')->nullable()->after('orientation_progress');
            });
        }
    }

    public function down(): void
    {
        Schema::table('kca_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('kca_applications', 'orientation_completed_at')) {
                $table->dropColumn('orientation_completed_at');
            }
            if (Schema::hasColumn('kca_applications', 'orientation_progress')) {
                $table->dropColumn('orientation_progress');
            }
        });
    }
};
