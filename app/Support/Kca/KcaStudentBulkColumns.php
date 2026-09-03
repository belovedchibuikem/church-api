<?php

namespace App\Support\Kca;

/**
 * Canonical spreadsheet columns for KCA student import/export.
 * Mirrors the admin registration wizard plus enrollment fields.
 */
final class KcaStudentBulkColumns
{
    public const YEARS_OPTIONS = ['Less than 1', '1–3 years', '3–5 years', '5+ years'];

    public const BAPTISED_OPTIONS = ['Yes', 'No', 'Preparing'];

    public const INTEREST_OPTIONS = ['Evangelism', 'Discipleship', 'Worship', 'Media', 'Children', 'Youth', 'Missions'];

    public const GUARDIAN_RELATIONSHIP_OPTIONS = ['Parent', 'Guardian', 'Spouse', 'Other'];

    /**
     * Ordered export/import headers.
     *
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'given_name',
            'family_name',
            'email',
            'phone',
            'create_login',
            'password',
            'person_id',
            'fullName',
            'church_id',
            'church_name',
            'home_church_id',
            'home_church_name',
            'pastor_id',
            'pastor_email',
            'years',
            'baptised',
            'story',
            'why',
            'interest',
            'interest2',
            'attendance_commitment',
            'conduct_commitment',
            'communication_commitment',
            'declaration_signature',
            'declaration_date',
            'declaration_confirmed',
            'guardian_name',
            'guardian_relationship',
            'guardian_phone',
            'guardian_email',
            'guardian_consent',
            'recommender_name',
            'recommender_position',
            'recommender_phone',
            'recommender_email',
            'cohort_id',
            'cohort_code',
            'registration_number',
            'starts_on',
            // Present on export only (ignored on import)
            'enrollment_id',
            'application_id',
            'application_status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sampleRow(): array
    {
        $row = array_fill_keys(self::headers(), '');
        $row['given_name'] = 'Ada';
        $row['family_name'] = 'Okafor';
        $row['email'] = 'ada.okafor@example.org';
        $row['phone'] = '+2348012345678';
        $row['create_login'] = 'yes';
        $row['password'] = 'ChangeMe123';
        $row['fullName'] = 'Ada Okafor';
        $row['church_name'] = 'Grace Chapel';
        $row['years'] = '3–5 years';
        $row['baptised'] = 'Yes';
        $row['story'] = 'Came to faith in 2018 and currently serves in discipleship.';
        $row['why'] = 'To grow in ministry leadership and serve the church.';
        $row['interest'] = 'Discipleship';
        $row['interest2'] = 'Evangelism';
        $row['attendance_commitment'] = 'yes';
        $row['conduct_commitment'] = 'yes';
        $row['communication_commitment'] = 'yes';
        $row['declaration_signature'] = 'Ada Okafor';
        $row['declaration_date'] = now()->toDateString();
        $row['declaration_confirmed'] = 'yes';
        $row['recommender_name'] = 'Pastor Daniel';
        $row['recommender_position'] = 'Lead Pastor';
        $row['recommender_phone'] = '+2348098765432';
        $row['recommender_email'] = 'pastor.daniel@example.org';
        $row['cohort_code'] = 'KCA-2026-A';
        $row['starts_on'] = now()->toDateString();

        return $row;
    }

    /**
     * @return list<string>
     */
    public static function requiredForApplication(): array
    {
        return [
            'given_name',
            'family_name',
            'email',
            'fullName',
            'years',
            'baptised',
            'story',
            'why',
            'interest',
            'attendance_commitment',
            'conduct_commitment',
            'communication_commitment',
            'declaration_signature',
            'declaration_date',
            'declaration_confirmed',
            'recommender_name',
            'recommender_position',
            'recommender_email',
        ];
    }
}
