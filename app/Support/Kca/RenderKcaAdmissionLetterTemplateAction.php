<?php

namespace App\Support\Kca;

use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Models\KcaGovernanceConfiguration;
use App\Models\Person;
use App\Support\Identity\PersonDisplayName;

final class RenderKcaAdmissionLetterTemplateAction
{
    public function __construct(
        private readonly ResolveKcaApplicationChurchName $resolver,
    ) {}

    public function forApplication(
        KcaApplication $application,
        ?KcaAdmissionLetter $letter = null,
        ?KcaGovernanceConfiguration $governance = null,
    ): string {
        $application->loadMissing(['person.profile', 'enrollment.cohort:id,name,public_id']);
        $governance ??= $this->resolver->governanceDefaults()
            ->loadMissing(['admissionLetterheadFile', 'admissionSignatureFile']);

        return $this->render(
            $this->template($governance),
            $this->context($application, $letter, $governance),
        );
    }

    /** @param  array<string, string>  $context */
    public function render(string $template, array $context): string
    {
        $rendered = preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            static function (array $matches) use ($context): string {
                $key = strtolower($matches[1]);

                return $context[$key] ?? '______________________________';
            },
            $template,
        );

        return is_string($rendered) ? trim($rendered) : trim($template);
    }

    public function template(KcaGovernanceConfiguration $governance): string
    {
        $configured = trim((string) ($governance->admission_letter_body_template ?? ''));

        return $configured !== '' ? $configured : KcaAdmissionLetterDefaultTemplate::body();
    }

    /** @return array<string, string> */
    private function context(
        KcaApplication $application,
        ?KcaAdmissionLetter $letter,
        KcaGovernanceConfiguration $governance,
    ): array {
        $person = $application->person;
        $data = is_array($application->application_data) ? $application->application_data : [];
        $applicantName = PersonDisplayName::of($person) ?: 'Applicant';
        $issuedAt = $letter?->issued_at ?? now()->utc();
        $batch = $letter?->batch_label ?: $this->resolver->batchLabel($application);

        return [
            'reference_code' => (string) ($letter?->reference_code ?? 'Pending'),
            'date' => $issuedAt->format('d/m/Y'),
            'issued_date' => $issuedAt->format('d/m/Y'),
            'applicant_name' => $applicantName,
            'applicant_first_name' => $this->firstName($person, $applicantName),
            'applicant_address' => $this->address($data),
            'applicant_phone' => $this->phone($person, $data),
            'church_name' => $this->resolver->fromApplicationData($data) ?? 'Your church',
            'kca_year' => $batch ?: 'Upcoming intake',
            'batch_label' => $batch ?: 'Upcoming intake',
            'programme_commencement' => $this->programmeValue($data, 'programme_commencement', $governance->admission_programme_commencement),
            'programme_completion' => $this->programmeValue($data, 'programme_completion', $governance->admission_programme_completion),
            'venue' => $this->programmeValue($data, 'venue', $governance->admission_programme_venue),
            'training_schedule' => $this->programmeValue($data, 'training_schedule', $governance->admission_programme_schedule),
            'training_day_time' => $this->programmeValue($data, 'training_schedule', $governance->admission_programme_schedule),
            'assigned_mentor' => $this->programmeValue($data, 'assigned_mentor', $governance->admission_programme_mentor),
            'signer_name' => (string) ($letter?->signer_name ?: $governance->admission_signer_name ?: 'Provost, KCA'),
            'signer_title' => (string) ($letter?->signer_title ?: $governance->admission_signer_title ?: 'Kingdom Change Agents'),
            'applicant_signature' => $letter?->applicant_signature_name ?: '______________________________',
            'applicant_acceptance_date' => $letter?->applicant_accepted_at?->format('d/m/Y') ?: '______________________________',
            'guardian_name' => $letter?->guardian_name ?: (string) (data_get($data, 'guardian_name') ?: '______________________________'),
            'guardian_signature' => $letter?->guardian_signature_name ?: '______________________________',
            'guardian_phone' => $letter?->guardian_phone ?: (string) (data_get($data, 'guardian_phone') ?: '______________________________'),
            'guardian_acceptance_date' => $letter?->guardian_confirmed_at?->format('d/m/Y') ?: '______________________________',
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function address(array $data): string
    {
        $parts = array_values(array_filter([
            data_get($data, 'address'),
            data_get($data, 'address_line_1'),
            data_get($data, 'address_line1'),
            data_get($data, 'address_line_2'),
            data_get($data, 'city'),
            data_get($data, 'state'),
            data_get($data, 'country'),
        ], static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));

        if ($parts !== []) {
            return implode(', ', array_map(static fn (string $part): string => trim($part), $parts));
        }

        $church = $this->resolver->fromApplicationData($data);

        return $church ?: '______________________________';
    }

    /** @param  array<string, mixed>  $data */
    private function phone(?Person $person, array $data): string
    {
        $fromData = data_get($data, 'phone') ?? data_get($data, 'mobile') ?? data_get($data, 'guardian_phone');
        if (is_string($fromData) && trim($fromData) !== '') {
            return trim($fromData);
        }

        return PersonDisplayName::phone($person) ?: '______________________________';
    }

    private function firstName(?Person $person, string $fullName): string
    {
        $given = $person?->profile?->given_name;
        if (is_string($given) && trim($given) !== '') {
            return trim($given);
        }

        $parts = preg_split('/\s+/', trim($fullName));

        return is_array($parts) && isset($parts[0]) && $parts[0] !== ''
            ? $parts[0]
            : $fullName;
    }

    /** @param  array<string, mixed>  $data */
    private function programmeValue(array $data, string $key, ?string $fallback): string
    {
        $fromData = data_get($data, $key);
        if (is_string($fromData) && trim($fromData) !== '') {
            return trim($fromData);
        }

        $fallback = trim((string) ($fallback ?? ''));

        return $fallback !== '' ? $fallback : 'To be announced';
    }
}
