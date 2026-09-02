<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_governance_configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_reference_prefix')) {
                $table->string('admission_reference_prefix', 40)->default('KCA/ADM')->after('admission_signature_file_asset_id');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_letter_body_template')) {
                $table->longText('admission_letter_body_template')->nullable()->after('admission_reference_prefix');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_programme_commencement')) {
                $table->string('admission_programme_commencement', 120)->nullable()->after('admission_letter_body_template');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_programme_completion')) {
                $table->string('admission_programme_completion', 120)->nullable()->after('admission_programme_commencement');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_programme_venue')) {
                $table->string('admission_programme_venue', 160)->nullable()->after('admission_programme_completion');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_programme_schedule')) {
                $table->string('admission_programme_schedule', 160)->nullable()->after('admission_programme_venue');
            }
            if (! Schema::hasColumn('kca_governance_configurations', 'admission_programme_mentor')) {
                $table->string('admission_programme_mentor', 160)->nullable()->after('admission_programme_schedule');
            }
        });

        Schema::table('kca_admission_letters', function (Blueprint $table) {
            if (! Schema::hasColumn('kca_admission_letters', 'applicant_accepted_at')) {
                $table->timestamp('applicant_accepted_at')->nullable()->after('issued_at');
            }
            if (! Schema::hasColumn('kca_admission_letters', 'applicant_signature_name')) {
                $table->string('applicant_signature_name', 160)->nullable()->after('applicant_accepted_at');
            }
            if (! Schema::hasColumn('kca_admission_letters', 'applicant_signature_file_asset_id')) {
                $table->unsignedBigInteger('applicant_signature_file_asset_id')->nullable()->after('applicant_signature_name');
            }
            if (! Schema::hasColumn('kca_admission_letters', 'guardian_name')) {
                $table->string('guardian_name', 160)->nullable()->after('applicant_signature_file_asset_id');
            }
            if (! Schema::hasColumn('kca_admission_letters', 'guardian_phone')) {
                $table->string('guardian_phone', 40)->nullable()->after('guardian_name');
            }
            if (! Schema::hasColumn('kca_admission_letters', 'guardian_signature_name')) {
                $table->string('guardian_signature_name', 160)->nullable()->after('guardian_phone');
            }
            if (! Schema::hasColumn('kca_admission_letters', 'guardian_confirmed_at')) {
                $table->timestamp('guardian_confirmed_at')->nullable()->after('guardian_signature_name');
            }
        });

        Schema::table('kca_admission_letters', function (Blueprint $table) {
            if (! $this->hasForeignKey('kca_admission_letters', 'kca_admission_applicant_signature_fk')) {
                $table->foreign('applicant_signature_file_asset_id', 'kca_admission_applicant_signature_fk')
                    ->references('id')->on('file_assets')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kca_admission_letters', function (Blueprint $table) {
            if ($this->hasForeignKey('kca_admission_letters', 'kca_admission_applicant_signature_fk')) {
                $table->dropForeign('kca_admission_applicant_signature_fk');
            }
            $drop = array_values(array_filter([
                Schema::hasColumn('kca_admission_letters', 'guardian_confirmed_at') ? 'guardian_confirmed_at' : null,
                Schema::hasColumn('kca_admission_letters', 'guardian_signature_name') ? 'guardian_signature_name' : null,
                Schema::hasColumn('kca_admission_letters', 'guardian_phone') ? 'guardian_phone' : null,
                Schema::hasColumn('kca_admission_letters', 'guardian_name') ? 'guardian_name' : null,
                Schema::hasColumn('kca_admission_letters', 'applicant_signature_file_asset_id') ? 'applicant_signature_file_asset_id' : null,
                Schema::hasColumn('kca_admission_letters', 'applicant_signature_name') ? 'applicant_signature_name' : null,
                Schema::hasColumn('kca_admission_letters', 'applicant_accepted_at') ? 'applicant_accepted_at' : null,
            ]));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('kca_governance_configurations', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('kca_governance_configurations', 'admission_programme_mentor') ? 'admission_programme_mentor' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_programme_schedule') ? 'admission_programme_schedule' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_programme_venue') ? 'admission_programme_venue' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_programme_completion') ? 'admission_programme_completion' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_programme_commencement') ? 'admission_programme_commencement' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_letter_body_template') ? 'admission_letter_body_template' : null,
                Schema::hasColumn('kca_governance_configurations', 'admission_reference_prefix') ? 'admission_reference_prefix' : null,
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
