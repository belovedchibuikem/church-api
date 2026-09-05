<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('press_publications')
            ->whereIn('status', ['published', 'distribution'])
            ->whereNotNull('published_at')
            ->whereNull('archived_at')
            ->where('availability', 'unavailable')
            ->update(['availability' => 'available']);
    }

    public function down(): void
    {
        // Catalogue repair; do not hide titles that were already published.
    }
};
