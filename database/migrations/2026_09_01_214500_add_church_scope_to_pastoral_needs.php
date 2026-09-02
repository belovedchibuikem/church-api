<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pastoral_needs')) {
            return;
        }

        Schema::table('pastoral_needs', function (Blueprint $table): void {
            if (! Schema::hasColumn('pastoral_needs', 'church_id')) {
                $table->foreignId('church_id')->nullable()->after('person_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('pastoral_needs', 'home_church_id')) {
                $table->foreignId('home_church_id')->nullable()->after('church_id')->constrained()->nullOnDelete();
            }
        });

        if (Schema::hasColumn('pastoral_needs', 'person_id')) {
            Schema::table('pastoral_needs', function (Blueprint $table): void {
                $table->foreignId('person_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pastoral_needs')) {
            return;
        }

        Schema::table('pastoral_needs', function (Blueprint $table): void {
            if (Schema::hasColumn('pastoral_needs', 'home_church_id')) {
                $table->dropConstrainedForeignId('home_church_id');
            }
            if (Schema::hasColumn('pastoral_needs', 'church_id')) {
                $table->dropConstrainedForeignId('church_id');
            }
        });
    }
};
