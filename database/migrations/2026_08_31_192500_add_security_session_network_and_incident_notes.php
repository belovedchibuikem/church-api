<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_sessions', function (Blueprint $table): void {
            $table->string('last_ip', 45)->nullable()->after('revocation_reason');
            $table->string('last_country', 2)->nullable()->after('last_ip');
        });

        Schema::table('safeguarding_incidents', function (Blueprint $table): void {
            $table->text('case_notes')->nullable()->after('restricted_summary');
        });
    }

    public function down(): void
    {
        Schema::table('security_sessions', function (Blueprint $table): void {
            $table->dropColumn(['last_ip', 'last_country']);
        });

        Schema::table('safeguarding_incidents', function (Blueprint $table): void {
            $table->dropColumn('case_notes');
        });
    }
};
