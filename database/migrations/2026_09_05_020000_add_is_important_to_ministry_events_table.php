<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ministry_events', function (Blueprint $table): void {
            $table->boolean('is_important')
                ->default(false)
                ->after('capacity');
            $table->index('is_important');
        });
    }

    public function down(): void
    {
        Schema::table('ministry_events', function (Blueprint $table): void {
            $table->dropIndex(['is_important']);
            $table->dropColumn('is_important');
        });
    }
};
