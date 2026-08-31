<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_church_applications', function (Blueprint $table) {
            $table->string('residence_family_name', 100)->nullable()->after('proposed_name');
            $table->json('meeting_schedules')->nullable()->after('meeting_time');
        });

        Schema::table('home_churches', function (Blueprint $table) {
            $table->json('meeting_schedules')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('home_church_applications', function (Blueprint $table) {
            $table->dropColumn(['residence_family_name', 'meeting_schedules']);
        });

        Schema::table('home_churches', function (Blueprint $table) {
            $table->dropColumn('meeting_schedules');
        });
    }
};
