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
        Schema::table('mission_soul_journeys', function (Blueprint $table) {
            $table->foreignId('connected_church_id')
                ->nullable()
                ->after('person_id')
                ->constrained('churches')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mission_soul_journeys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connected_church_id');
        });
    }
};
