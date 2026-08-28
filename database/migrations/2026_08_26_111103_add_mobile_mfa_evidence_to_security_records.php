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
        Schema::table('mfa_methods', function (Blueprint $table) {
            $table->string('secret_hash')->nullable()->change();
            $table->text('encrypted_secret')->nullable()->after('secret_hash');
            $table->unsignedBigInteger('last_totp_counter')->nullable()->after('last_used_at');
        });

        Schema::table('security_sessions', function (Blueprint $table) {
            $table->foreignId('mfa_method_id')
                ->nullable()
                ->after('device_id')
                ->constrained('mfa_methods')
                ->nullOnDelete();
            $table->timestamp('mfa_verified_at')->nullable()->after('last_seen_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_sessions', function (Blueprint $table) {
            $table->dropForeign(['mfa_method_id']);
            $table->dropIndex(['mfa_verified_at']);
            $table->dropColumn(['mfa_method_id', 'mfa_verified_at']);
        });

        Schema::table('mfa_methods', function (Blueprint $table) {
            $table->dropColumn(['encrypted_secret', 'last_totp_counter']);
        });
    }
};
