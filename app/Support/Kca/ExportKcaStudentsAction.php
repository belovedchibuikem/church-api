<?php

namespace App\Support\Kca;

use App\Models\KcaEnrollment;
use App\Support\Identity\PersonDisplayName;
use App\Support\Spreadsheet\TabularSpreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportKcaStudentsAction
{
    public function template(): StreamedResponse
    {
        $headers = KcaStudentBulkColumns::headers();
        $sample = KcaStudentBulkColumns::sampleRow();

        return TabularSpreadsheet::streamCsv(
            'kca-students-import-template.csv',
            $headers,
            [$sample],
        );
    }

    public function enrolledStudents(): StreamedResponse
    {
        $headers = KcaStudentBulkColumns::headers();
        $rows = [];

        KcaEnrollment::query()
            ->with([
                'person.profile',
                'person.user:id,person_id,email',
                'application',
                'cohort:id,public_id,code,name',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($enrollments) use (&$rows): void {
                foreach ($enrollments as $enrollment) {
                    /** @var KcaEnrollment $enrollment */
                    $rows[] = $this->mapEnrollment($enrollment);
                }
            });

        return TabularSpreadsheet::streamCsv(
            'kca-students-export-'.now()->format('Ymd-His').'.csv',
            $headers,
            $rows,
        );
    }

    /**
     * @return array<string, string>
     */
    private function mapEnrollment(KcaEnrollment $enrollment): array
    {
        $row = array_fill_keys(KcaStudentBulkColumns::headers(), '');
        $person = $enrollment->person;
        $profile = $person?->profile;
        $application = $enrollment->application;
        $data = is_array($application?->application_data) ? $application->application_data : [];

        $row['given_name'] = (string) ($profile?->given_name ?? '');
        $row['family_name'] = (string) ($profile?->family_name ?? '');
        $row['email'] = (string) ($data['email'] ?? $person?->user?->email ?? '');
        $row['phone'] = (string) ($profile?->phone ?? '');
        $row['person_id'] = (string) ($person?->public_id ?? '');
        $row['fullName'] = (string) ($data['fullName'] ?? PersonDisplayName::of($person) ?? '');
        $row['church_id'] = (string) ($data['church_id'] ?? '');
        $row['church_name'] = (string) (app(ResolveKcaApplicationChurchName::class)->fromApplicationData($data) ?? '');
        $row['home_church_id'] = (string) ($data['home_church_id'] ?? '');
        $row['pastor_id'] = (string) ($data['pastor_id'] ?? '');
        $row['years'] = (string) ($data['years'] ?? '');
        $row['baptised'] = (string) ($data['baptised'] ?? '');
        $row['story'] = (string) ($data['story'] ?? '');
        $row['why'] = (string) ($data['why'] ?? '');
        $row['interest'] = (string) ($data['interest'] ?? '');
        $row['interest2'] = (string) ($data['interest2'] ?? '');
        $row['attendance_commitment'] = $this->flag($data['attendance_commitment'] ?? null);
        $row['conduct_commitment'] = $this->flag($data['conduct_commitment'] ?? null);
        $row['communication_commitment'] = $this->flag($data['communication_commitment'] ?? null);
        $row['declaration_signature'] = (string) ($data['declaration_signature'] ?? '');
        $row['declaration_date'] = (string) ($data['declaration_date'] ?? '');
        $row['declaration_confirmed'] = $this->flag($data['declaration_confirmed'] ?? null);
        $row['guardian_name'] = (string) ($data['guardian_name'] ?? '');
        $row['guardian_relationship'] = (string) ($data['guardian_relationship'] ?? '');
        $row['guardian_phone'] = (string) ($data['guardian_phone'] ?? '');
        $row['guardian_email'] = (string) ($data['guardian_email'] ?? '');
        $row['guardian_consent'] = $this->flag($data['guardian_consent'] ?? null);
        $row['recommender_name'] = (string) ($data['recommender_name'] ?? '');
        $row['recommender_position'] = (string) ($data['recommender_position'] ?? $data['recommender_role'] ?? '');
        $row['recommender_phone'] = (string) ($data['recommender_phone'] ?? '');
        $row['recommender_email'] = (string) ($data['recommender_email'] ?? '');
        $row['cohort_id'] = (string) ($enrollment->cohort?->public_id ?? '');
        $row['cohort_code'] = (string) ($enrollment->cohort?->code ?? '');
        $row['registration_number'] = (string) ($enrollment->registration_number ?? '');
        $row['starts_on'] = (string) ($enrollment->starts_on?->toDateString() ?? '');
        $row['enrollment_id'] = (string) $enrollment->public_id;
        $row['application_id'] = (string) ($application?->public_id ?? '');
        $row['application_status'] = (string) ($application?->status?->value ?? $application?->status ?? '');
        $row['create_login'] = '';
        $row['password'] = '';
        $row['home_church_name'] = '';
        $row['pastor_email'] = '';

        return $row;
    }

    private function flag(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true) ? 'yes' : 'no';
    }
}
