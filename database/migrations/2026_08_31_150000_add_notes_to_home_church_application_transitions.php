<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_church_application_transitions', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('reason_code');
        });
    }

    public function down(): void
    {
        Schema::table('home_church_application_transitions', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
