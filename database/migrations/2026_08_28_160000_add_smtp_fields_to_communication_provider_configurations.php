<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_provider_configurations', function (Blueprint $table) {
            $table->string('email_smtp_host', 191)->nullable()->after('email_api_key');
            $table->unsignedSmallInteger('email_smtp_port')->nullable()->after('email_smtp_host');
            $table->string('email_smtp_username', 191)->nullable()->after('email_smtp_port');
            $table->string('email_smtp_encryption', 8)->nullable()->after('email_smtp_username');
        });
    }

    public function down(): void
    {
        Schema::table('communication_provider_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'email_smtp_host',
                'email_smtp_port',
                'email_smtp_username',
                'email_smtp_encryption',
            ]);
        });
    }
};
