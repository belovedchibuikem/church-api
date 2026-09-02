<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('home_church_attendance_records')) {
            return;
        }

        Schema::table('home_church_attendance_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('home_church_attendance_records', 'males')) {
                $table->unsignedInteger('males')->default(0)->after('adults');
            }
            if (! Schema::hasColumn('home_church_attendance_records', 'females')) {
                $table->unsignedInteger('females')->default(0)->after('males');
            }
        });

        DB::table('home_church_attendance_records')
            ->where('males', 0)
            ->where('females', 0)
            ->where('adults', '>', 0)
            ->update(['males' => DB::raw('adults')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('home_church_attendance_records')) {
            return;
        }

        Schema::table('home_church_attendance_records', function (Blueprint $table): void {
            if (Schema::hasColumn('home_church_attendance_records', 'females')) {
                $table->dropColumn('females');
            }
            if (Schema::hasColumn('home_church_attendance_records', 'males')) {
                $table->dropColumn('males');
            }
        });
    }
};
