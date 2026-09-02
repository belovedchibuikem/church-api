<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_governance_configurations', function (Blueprint $table): void {
            $table->text('orientation_welcome')->nullable()->after('admission_programme_mentor');
            $table->text('orientation_review_welcome')->nullable()->after('orientation_welcome');
        });
    }

    public function down(): void
    {
        Schema::table('kca_governance_configurations', function (Blueprint $table): void {
            $table->dropColumn(['orientation_welcome', 'orientation_review_welcome']);
        });
    }
};
