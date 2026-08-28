<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table): void {
            $table->foreignId('assigned_to_person_id')->nullable()->after('person_id')->constrained('people')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to_person_id');
            $table->dropColumn('assigned_at');
        });
    }
};
