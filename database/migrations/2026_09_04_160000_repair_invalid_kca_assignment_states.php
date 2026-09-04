<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('kca_assignments')
            ->whereIn('state', ['published', 'publised', 'publish'])
            ->update([
                'state' => 'assigned',
                'assigned_at' => DB::raw('COALESCE(assigned_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        // Invalid labels were data errors; do not restore them.
    }
};
