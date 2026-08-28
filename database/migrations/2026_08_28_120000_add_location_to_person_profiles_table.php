<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->string('country', 8)->nullable()->after('preferred_name');
            $table->string('region', 120)->nullable()->after('country');
            $table->string('locality', 120)->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->dropColumn(['country', 'region', 'locality']);
        });
    }
};
