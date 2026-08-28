<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ministry_events', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('capacity');
            $table->index(
                ['published_at', 'ends_at', 'starts_at'],
                'ministry_events_public_upcoming_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ministry_events', function (Blueprint $table) {
            $table->dropIndex('ministry_events_public_upcoming_index');
            $table->dropColumn('published_at');
        });
    }
};
