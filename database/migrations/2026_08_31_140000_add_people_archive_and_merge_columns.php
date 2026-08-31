<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (! Schema::hasColumn('people', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('public_id');
            }
            if (! Schema::hasColumn('people', 'merged_into_person_id')) {
                $table->foreignId('merged_into_person_id')->nullable()->after('archived_at')->constrained('people')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (Schema::hasColumn('people', 'merged_into_person_id')) {
                $table->dropConstrainedForeignId('merged_into_person_id');
            }
            if (Schema::hasColumn('people', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
