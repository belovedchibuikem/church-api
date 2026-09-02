<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_governance_configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_signer_name')) {
                $table->string('admission_signer_name', 120)->nullable()->after('certificate_signer_title');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_signer_title')) {
                $table->string('admission_signer_title', 120)->nullable()->after('admission_signer_name');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_letterhead_file_asset_id')) {
                $table->unsignedBigInteger('admission_letterhead_file_asset_id')->nullable()->after('admission_signer_title');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_signature_file_asset_id')) {
                $table->unsignedBigInteger('admission_signature_file_asset_id')->nullable()->after('admission_letterhead_file_asset_id');
            }
        });

        Schema::table('kca_governance_configurations', function (Blueprint $table) {
            if (! $this->hasForeignKey('kca_governance_configurations', 'kca_gov_admission_letterhead_fk')) {
                $table->foreign('admission_letterhead_file_asset_id', 'kca_gov_admission_letterhead_fk')
                    ->references('id')->on('file_assets')->nullOnDelete();
            }
            if (! $this->hasForeignKey('kca_governance_configurations', 'kca_gov_admission_signature_fk')) {
                $table->foreign('admission_signature_file_asset_id', 'kca_gov_admission_signature_fk')
                    ->references('id')->on('file_assets')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kca_governance_configurations', function (Blueprint $table) {
            if ($this->hasForeignKey('kca_governance_configurations', 'kca_gov_admission_signature_fk')) {
                $table->dropForeign('kca_gov_admission_signature_fk');
            }
            if ($this->hasForeignKey('kca_governance_configurations', 'kca_gov_admission_letterhead_fk')) {
                $table->dropForeign('kca_gov_admission_letterhead_fk');
            }
            $drop = array_values(array_filter([
                Schema::hasColumn('kca_governance_configurations', 'admission_signature_file_asset_id')
                    ? 'admission_signature_file_asset_id' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_letterhead_file_asset_id')
                    ? 'admission_letterhead_file_asset_id' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_signer_title')
                    ? 'admission_signer_title' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_signer_name')
                    ? 'admission_signer_name' : null,
            ]));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [$database, $table, $name],
        );

        return $result !== [];
    }
};
