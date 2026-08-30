<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->foreignId('avatar_file_asset_id')
                ->nullable()
                ->after('locality')
                ->constrained('file_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_file_asset_id');
        });
    }
};
