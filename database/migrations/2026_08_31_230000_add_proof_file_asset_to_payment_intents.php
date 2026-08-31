<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_intents', 'proof_file_asset_id')) {
                $table->foreignId('proof_file_asset_id')
                    ->nullable()
                    ->after('succeeded_at')
                    ->constrained('file_assets')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_intents', 'proof_file_asset_id')) {
                $table->dropConstrainedForeignId('proof_file_asset_id');
            }
        });
    }
};
