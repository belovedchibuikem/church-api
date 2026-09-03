<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\Church;
use App\Models\HomeChurch;
use App\Models\KcaCohort;
use App\Models\Person;
use App\Models\User;
use App\Support\Spreadsheet\TabularSpreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ImportKcaStudentsAction
{
    public function __construct(
        private CreateAdminKcaApplicationAction $createApplication,
        private TransitionKcaApplicationToStatusAction $transitionApplication,
        private EnrollKcaStudentAction $enrollStudent,
    ) {}

    /**
     * @return array{
     *   mode: string,
     *   total_rows: int,
     *   imported_count: int,
     *   failed_count: int,
     *   imported: list<array<string, mixed>>,
     *   failures: list<array{row: int, error: string, email?: string|null, given_name?: string|null, family_name?: string|null}>
     * }
     */
    public function handle(UploadedFile $file, User $actor, string $mode = 'enroll'): array
    {
        $mode = strtolower(trim($mode));
        if (! in_array($mode, ['enroll', 'application'], true)) {
            throw new InvalidArgumentException('Import mode must be enroll or application.');
        }

        $rows = TabularSpreadsheet::readAssociativeRows(
            $file->getRealPath() ?: $file->getPathname(),
            $file->getClientOriginalName() ?: 'upload.csv',
        );

        $imported = [];
        $failures = [];

        foreach ($rows as $index => $row) {
            $sheetRow = $index + 2; // header is row 1
            try {
                $imported[] = $this->importRow($row, $actor, $mode, $sheetRow);
            } catch (Throwable $exception) {
                $failures[] = [
                    'row' => $sheetRow,
                    'error' => $exception->getMessage(),
                    'email' => $this->cell($row, 'email') ?: null,
                    'given_name' => $this->cell($row, 'given_name') ?: null,
                    'family_name' => $this->cell($row, 'family_name') ?: null,
                ];
            }
        }

        return [
            'mode' => $mode,
            'total_rows' => count($rows),
            'imported_count' => count($imported),
            'failed_count' => count($failures),
            'imported' => $imported,
            'failures' => $failures,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function importRow(array $row, User $actor, string $mode, int $sheetRow): array
    {
        $normalized = $this->normalizeRow($row);
        $this->assertRequired($normalized, $mode);

        $person = $this->resolvePerson(
            $normalized['person_id'] ?? null,
            $normalized['email'] ?? null,
        );

        $churchId = $this->resolveChurchId(
            $normalized['church_id'] ?? null,
            $normalized['church_name'] ?? null,
        );
        if ($churchId === null) {
            throw new InvalidArgumentException('church_id or church_name is required and must match an existing church.');
        }

        $homeChurchId = $this->resolveHomeChurchId(
            $normalized['home_church_id'] ?? null,
            $normalized['home_church_name'] ?? null,
        );

        $pastorId = $this->resolvePastorId(
            $normalized['pastor_id'] ?? null,
            $normalized['pastor_email'] ?? null,
        );

        $createLogin = $this->isTruthy($normalized['create_login'] ?? null);
        $password = trim((string) ($normalized['password'] ?? ''));
        if ($createLogin && $password === '') {
            throw new InvalidArgumentException('password is required when create_login is yes.');
        }

        $applicationData = $this->buildApplicationData($normalized, $churchId, $homeChurchId, $pastorId);

        $application = $this->createApplication->handle(
            null,
            $person,
            $applicationData,
            $actor,
            $person ? null : ($normalized['given_name'] ?? null),
            $person ? null : ($normalized['family_name'] ?? null),
            $normalized['email'] ?? null,
            $person ? null : ($normalized['phone'] ?? null),
            true,
            $createLogin,
            $createLogin ? $password : null,
        );

        $enrollmentPayload = null;
        if ($mode === 'enroll') {
            $cohort = $this->resolveCohort(
                $normalized['cohort_id'] ?? null,
                $normalized['cohort_code'] ?? null,
            );
            if ($cohort === null) {
                throw new InvalidArgumentException('cohort_id or cohort_code is required when importing with enroll mode.');
            }

            $startsOnRaw = trim((string) ($normalized['starts_on'] ?? ''));
            if ($startsOnRaw === '') {
                $startsOnRaw = optional($cohort->starts_on)?->toDateString() ?? now()->toDateString();
            }
            $startsOn = $this->parseDate($startsOnRaw, 'starts_on');

            $application = $this->transitionApplication->handle(
                $application,
                KcaApplicationState::Accepted,
                $actor,
            );

            $registrationNumber = trim((string) ($normalized['registration_number'] ?? ''));
            $enrollment = $this->enrollStudent->handle(
                $application,
                $cohort,
                $registrationNumber !== '' ? $registrationNumber : null,
                $startsOn,
                $actor,
            );

            $enrollmentPayload = [
                'id' => $enrollment->public_id,
                'registration_number' => $enrollment->registration_number,
                'cohort_id' => $cohort->public_id,
                'starts_on' => $enrollment->starts_on?->toDateString(),
            ];
        }

        return [
            'row' => $sheetRow,
            'application_id' => $application->public_id,
            'application_status' => $application->status instanceof KcaApplicationState
                ? $application->status->value
                : (string) $application->status,
            'person_id' => $application->person?->public_id,
            'email' => $normalized['email'] ?? null,
            'enrollment' => $enrollmentPayload,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        $aliases = [
            'firstname' => 'given_name',
            'first_name' => 'given_name',
            'lastname' => 'family_name',
            'last_name' => 'family_name',
            'fullname_name' => 'fullName',
            'fullname_name_application' => 'fullName',
            'church' => 'church_name',
            'sponsoring_church' => 'church_name',
            'sponsoring_church_id' => 'church_id',
            'home_church' => 'home_church_name',
            'cohort' => 'cohort_code',
            'batch' => 'cohort_code',
            'batch_code' => 'cohort_code',
            'reg_number' => 'registration_number',
            'start_date' => 'starts_on',
            'start_on' => 'starts_on',
            'recommender_role' => 'recommender_position',
            'primary_interest' => 'interest',
            'secondary_interest' => 'interest2',
        ];

        $normalized = [];
        foreach ($row as $key => $value) {
            $header = strtolower(trim(str_replace([' ', '-'], '_', (string) $key)));
            $header = $aliases[$header] ?? $header;
            $normalized[$header] = trim((string) $value);
        }

        if (($normalized['fullName'] ?? '') === '' && ($normalized['given_name'] ?? '') !== '' && ($normalized['family_name'] ?? '') !== '') {
            $normalized['fullName'] = trim($normalized['given_name'].' '.$normalized['family_name']);
        }

        foreach (['declaration_date', 'starts_on'] as $dateField) {
            if (($normalized[$dateField] ?? '') !== '') {
                $normalized[$dateField] = $this->parseDate($normalized[$dateField], $dateField)->toDateString();
            }
        }

        foreach ([
            'create_login',
            'attendance_commitment',
            'conduct_commitment',
            'communication_commitment',
            'declaration_confirmed',
            'guardian_consent',
        ] as $flag) {
            if (array_key_exists($flag, $normalized) && $normalized[$flag] !== '') {
                $normalized[$flag] = $this->isTruthy($normalized[$flag]) ? 'true' : '';
            }
        }

        if (($normalized['years'] ?? '') !== '') {
            $normalized['years'] = $this->matchOption($normalized['years'], KcaStudentBulkColumns::YEARS_OPTIONS, 'years');
        }
        if (($normalized['baptised'] ?? '') !== '') {
            $normalized['baptised'] = $this->matchOption($normalized['baptised'], KcaStudentBulkColumns::BAPTISED_OPTIONS, 'baptised');
        }
        if (($normalized['interest'] ?? '') !== '') {
            $normalized['interest'] = $this->matchOption($normalized['interest'], KcaStudentBulkColumns::INTEREST_OPTIONS, 'interest');
        }
        if (($normalized['interest2'] ?? '') !== '') {
            $normalized['interest2'] = $this->matchOption($normalized['interest2'], KcaStudentBulkColumns::INTEREST_OPTIONS, 'interest2');
        }
        if (($normalized['guardian_relationship'] ?? '') !== '') {
            $normalized['guardian_relationship'] = $this->matchOption(
                $normalized['guardian_relationship'],
                KcaStudentBulkColumns::GUARDIAN_RELATIONSHIP_OPTIONS,
                'guardian_relationship',
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function assertRequired(array $row, string $mode): void
    {
        if (($row['person_id'] ?? '') === '') {
            foreach (['given_name', 'family_name'] as $required) {
                if (($row[$required] ?? '') === '') {
                    throw new InvalidArgumentException("{$required} is required when person_id is blank.");
                }
            }
        }

        if (($row['email'] ?? '') === '' || ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required.');
        }

        foreach (KcaStudentBulkColumns::requiredForApplication() as $required) {
            if (in_array($required, ['given_name', 'family_name', 'email'], true)) {
                continue;
            }
            if (($row[$required] ?? '') === '') {
                throw new InvalidArgumentException("{$required} is required.");
            }
        }

        if (! $this->isTruthy($row['attendance_commitment'] ?? null)
            || ! $this->isTruthy($row['conduct_commitment'] ?? null)
            || ! $this->isTruthy($row['communication_commitment'] ?? null)
            || ! $this->isTruthy($row['declaration_confirmed'] ?? null)
        ) {
            throw new InvalidArgumentException('All commitment and declaration_confirmed columns must be yes/true.');
        }

        if ($mode === 'enroll' && ($row['cohort_id'] ?? '') === '' && ($row['cohort_code'] ?? '') === '') {
            throw new InvalidArgumentException('cohort_id or cohort_code is required for enroll mode.');
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string|null>
     */
    private function buildApplicationData(array $row, string $churchId, ?string $homeChurchId, ?string $pastorId): array
    {
        $data = [
            'fullName' => $row['fullName'],
            'email' => $row['email'],
            'church_id' => $churchId,
            'years' => $row['years'],
            'baptised' => $row['baptised'],
            'story' => $row['story'],
            'why' => $row['why'],
            'interest' => $row['interest'],
            'attendance_commitment' => 'true',
            'conduct_commitment' => 'true',
            'communication_commitment' => 'true',
            'declaration_signature' => $row['declaration_signature'],
            'declaration_date' => $row['declaration_date'],
            'declaration_confirmed' => 'true',
            'recommender_name' => $row['recommender_name'],
            'recommender_position' => $row['recommender_position'],
            'recommender_email' => $row['recommender_email'],
        ];

        foreach ([
            'interest2',
            'guardian_name',
            'guardian_relationship',
            'guardian_phone',
            'guardian_email',
            'recommender_phone',
        ] as $optional) {
            if (($row[$optional] ?? '') !== '') {
                $data[$optional] = $row[$optional];
            }
        }

        if ($homeChurchId !== null) {
            $data['home_church_id'] = $homeChurchId;
        }
        if ($pastorId !== null) {
            $data['pastor_id'] = $pastorId;
        }
        if ($this->isTruthy($row['guardian_consent'] ?? null)) {
            $data['guardian_consent'] = 'true';
        }

        return $data;
    }

    private function resolvePerson(?string $personId, ?string $email): ?Person
    {
        $personId = trim((string) $personId);
        if ($personId !== '') {
            $person = Person::query()->where('public_id', $personId)->first();
            if ($person === null) {
                throw new InvalidArgumentException("person_id {$personId} was not found.");
            }

            return $person;
        }

        $email = Str::lower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        return $user?->person;
    }

    private function resolveChurchId(?string $churchId, ?string $churchName): ?string
    {
        $churchId = trim((string) $churchId);
        if ($churchId !== '') {
            $church = Church::query()->where('public_id', $churchId)->first();
            if ($church === null) {
                throw new InvalidArgumentException("church_id {$churchId} was not found.");
            }

            return $church->public_id;
        }

        $churchName = trim((string) $churchName);
        if ($churchName === '') {
            return null;
        }

        $matches = Church::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($churchName)])
            ->get(['id', 'public_id', 'name']);

        if ($matches->count() === 0) {
            throw new InvalidArgumentException("No church found with name \"{$churchName}\".");
        }
        if ($matches->count() > 1) {
            throw new InvalidArgumentException("Multiple churches match \"{$churchName}\". Use church_id instead.");
        }

        return $matches->first()->public_id;
    }

    private function resolveHomeChurchId(?string $homeChurchId, ?string $homeChurchName): ?string
    {
        $homeChurchId = trim((string) $homeChurchId);
        if ($homeChurchId !== '') {
            $homeChurch = HomeChurch::query()->where('public_id', $homeChurchId)->first();
            if ($homeChurch === null) {
                throw new InvalidArgumentException("home_church_id {$homeChurchId} was not found.");
            }

            return $homeChurch->public_id;
        }

        $homeChurchName = trim((string) $homeChurchName);
        if ($homeChurchName === '') {
            return null;
        }

        $matches = HomeChurch::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($homeChurchName)])
            ->get(['id', 'public_id', 'name']);

        if ($matches->count() === 0) {
            throw new InvalidArgumentException("No home church found with name \"{$homeChurchName}\".");
        }
        if ($matches->count() > 1) {
            throw new InvalidArgumentException("Multiple home churches match \"{$homeChurchName}\". Use home_church_id instead.");
        }

        return $matches->first()->public_id;
    }

    private function resolvePastorId(?string $pastorId, ?string $pastorEmail): ?string
    {
        $pastorId = trim((string) $pastorId);
        if ($pastorId !== '') {
            $person = Person::query()->where('public_id', $pastorId)->first();
            if ($person === null) {
                throw new InvalidArgumentException("pastor_id {$pastorId} was not found.");
            }

            return $person->public_id;
        }

        $pastorEmail = Str::lower(trim((string) $pastorEmail));
        if ($pastorEmail === '') {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$pastorEmail])->first();
        if ($user?->person === null) {
            throw new InvalidArgumentException("No person linked to pastor_email {$pastorEmail}.");
        }

        return $user->person->public_id;
    }

    private function resolveCohort(?string $cohortId, ?string $cohortCode): ?KcaCohort
    {
        $cohortId = trim((string) $cohortId);
        if ($cohortId !== '') {
            $cohort = KcaCohort::query()->where('public_id', $cohortId)->first();
            if ($cohort === null) {
                throw new InvalidArgumentException("cohort_id {$cohortId} was not found.");
            }

            return $cohort;
        }

        $cohortCode = trim((string) $cohortCode);
        if ($cohortCode === '') {
            return null;
        }

        $matches = KcaCohort::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower($cohortCode)])
            ->get();

        if ($matches->count() === 0) {
            $matches = KcaCohort::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($cohortCode)])
                ->get();
        }

        if ($matches->count() === 0) {
            throw new InvalidArgumentException("No cohort found for \"{$cohortCode}\".");
        }
        if ($matches->count() > 1) {
            throw new InvalidArgumentException("Multiple cohorts match \"{$cohortCode}\". Use cohort_id instead.");
        }

        return $matches->first();
    }

    private function parseDate(string $value, string $field): CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("{$field} is required.");
        }

        // Excel serial date (days since 1899-12-30)
        if (preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            $serial = (float) $value;
            if ($serial > 20000 && $serial < 80000) {
                return CarbonImmutable::create(1899, 12, 30)->addDays((int) floor($serial))->startOfDay();
            }
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            throw new InvalidArgumentException("{$field} must be a valid date (YYYY-MM-DD).");
        }
    }

    /**
     * @param  list<string>  $options
     */
    private function matchOption(string $value, array $options, string $field): string
    {
        $value = trim($value);
        foreach ($options as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        // Allow ASCII hyphen variants for years option
        $compact = str_replace(['-', '–', '—'], '-', $value);
        foreach ($options as $option) {
            if (strcasecmp(str_replace(['-', '–', '—'], '-', $option), $compact) === 0) {
                return $option;
            }
        }

        throw new InvalidArgumentException(
            "{$field} must be one of: ".implode(', ', $options).".",
        );
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        $normalized = Str::lower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function cell(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }
}
