<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_admission_letters', function (Blueprint $table) {
            if (! Schema::hasColumn('kca_admission_letters', 'registration_number')) {
                $table->string('registration_number', 100)->nullable()->after('reference_code');
                $table->unique('registration_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kca_admission_letters', function (Blueprint $table) {
            if (Schema::hasColumn('kca_admission_letters', 'registration_number')) {
                $table->dropUnique(['registration_number']);
                $table->dropColumn('registration_number');
            }
        });
    }
};
