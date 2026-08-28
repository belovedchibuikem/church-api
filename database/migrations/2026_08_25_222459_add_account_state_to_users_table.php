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
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 30)->default('active')->after('password');
            $table->string('suspension_reason', 191)->nullable()->after('account_status');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
            $table->timestamp('reactivated_at')->nullable()->after('suspended_at');

            $table->index(['account_status', 'suspended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status', 'suspended_at']);
            $table->dropColumn([
                'account_status',
                'suspension_reason',
                'suspended_at',
                'reactivated_at',
            ]);
        });
    }
};
