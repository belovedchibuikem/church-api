<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_publications', function (Blueprint $table) {
            if (! Schema::hasColumn('press_publications', 'content_source_url')) {
                $table->string('content_source_url', 2048)->nullable()->after('content_file_asset_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('press_publications', function (Blueprint $table) {
            if (Schema::hasColumn('press_publications', 'content_source_url')) {
                $table->dropColumn('content_source_url');
            }
        });
    }
};
