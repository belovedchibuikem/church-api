<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_applications', function (Blueprint $table): void {
            $table->json('application_data')->nullable()->after('person_id');
        });
    }

    public function down(): void
    {
        Schema::table('kca_applications', function (Blueprint $table): void {
            $table->dropColumn('application_data');
        });
    }
};
