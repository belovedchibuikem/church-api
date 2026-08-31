<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('local_name', 191)->nullable()->after('name');
            $table->string('calling_code', 8)->nullable()->after('local_name');
            $table->char('currency_code', 3)->nullable()->after('calling_code');
            $table->string('default_timezone', 64)->nullable()->after('currency_code');
            $table->string('locale', 12)->nullable()->after('default_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['local_name', 'calling_code', 'currency_code', 'default_timezone', 'locale']);
        });
    }
};
