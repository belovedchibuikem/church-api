<?php

namespace App\Support\Kca;

use App\Models\KcaApplication;
use App\Models\Person;
use App\Support\Identity\PersonDisplayName;

/**
 * Flattens the 8–9 KCA registration steps into labelled fields for admin view.
 */
final class KcaRegistrationProfile
{
    /** @var array<string, string> */
    public const FIELD_LABELS = [
        'person_name' => 'Full name',
        'given_name' => 'Given name',
        'family_name' => 'Family name',
        'email' => 'Email address',
        'phone' => 'Phone number',
        'fullName' => 'Full name (application)',
        'church_name' => 'Sponsoring church',
        'home_church_name' => 'Home church',
        'church_ministry' => 'Church ministry',
        'church_address' => 'Church address',
        'pastor_name' => 'Pastor / leader',
        'pastors_name' => 'Pastor / leader name',
        'pastors_phone' => 'Pastor / leader phone',
        'country' => 'Country',
        'country_label' => 'Country',
        'region' => 'Region',
        'locality' => 'Locality',
        'years' => 'Years walking with Christ',
        'baptised' => 'Baptised?',
        'story' => 'Walk with Christ',
        'why' => 'Why join KCA',
        'interest' => 'Primary interest',
        'interest2' => 'Secondary interest',
        'attendance_commitment' => 'Attendance & assignments commitment',
        'conduct_commitment' => 'Conduct commitment',
        'communication_commitment' => 'Communication commitment',
        'declaration_signature' => 'Declaration signature',
        'declaration_date' => 'Declaration date',
        'declaration_confirmed' => 'Information is true and complete',
        'guardian_name' => 'Guardian / parent full name',
        'guardian_relationship' => 'Relationship to applicant',
        'guardian_phone' => 'Guardian phone number',
        'guardian_email' => 'Guardian email address',
        'guardian_consent' => 'Guardian consent provided',
        'recommender_name' => 'Church leader name',
        'recommender_position' => 'Church leader role',
        'recommender_phone' => 'Church leader phone',
        'recommender_email' => 'Church leader email',
    ];

    /** @var array<string, list<string>> */
    public const SECTIONS = [
        'Personal details' => ['person_name', 'given_name', 'family_name', 'email', 'phone', 'fullName'],
        'Church Information' => ['church_name', 'home_church_name', 'church_ministry', 'church_address', 'pastor_name', 'pastors_name', 'pastors_phone', 'country', 'country_label', 'region', 'locality'],
        'Walk With Christ' => ['years', 'baptised', 'story'],
        'Why Join KCA' => ['why'],
        'Kingdom Interests' => ['interest', 'interest2'],
        'Commitment' => ['attendance_commitment', 'conduct_commitment', 'communication_commitment'],
        'Personal Declaration' => ['declaration_signature', 'declaration_date', 'declaration_confirmed'],
        'Parent / Guardian Consent' => ['guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email', 'guardian_consent'],
        'Leadership Recommendation' => ['recommender_email', 'recommender_name', 'recommender_position', 'recommender_phone'],
    ];

    /**
     * @return array<string, string>
     */
    public static function flattened(?KcaApplication $application, ?Person $person = null): array
    {
        $person ??= $application?->person;
        $data = is_array($application?->application_data) ? $application->application_data : [];
        $resolver = app(ResolveKcaApplicationChurchName::class);

        $values = [
            'person_name' => PersonDisplayName::of($person),
            'given_name' => trim((string) ($person?->profile?->given_name ?? '')),
            'family_name' => trim((string) ($person?->profile?->family_name ?? '')),
            'email' => PersonDisplayName::email($person) ?: self::stringValue($data, 'email'),
            'phone' => PersonDisplayName::phone($person)
                ?: self::stringValue($data, 'phone')
                ?: self::stringValue($data, 'mobile'),
            'fullName' => self::stringValue($data, 'fullName'),
            'church_name' => $resolver->fromApplicationData($data) ?? self::stringValue($data, 'church_name'),
            'home_church_name' => self::stringValue($data, 'home_church_name'),
            'church_ministry' => self::stringValue($data, 'church_ministry'),
            'church_address' => self::stringValue($data, 'church_address'),
            'pastor_name' => self::stringValue($data, 'pastor_name'),
            'pastors_name' => self::stringValue($data, 'pastors_name'),
            'pastors_phone' => self::stringValue($data, 'pastors_phone'),
            'country' => self::stringValue($data, 'country'),
            'country_label' => self::stringValue($data, 'country_label'),
            'region' => self::stringValue($data, 'region'),
            'locality' => self::stringValue($data, 'locality'),
            'years' => self::stringValue($data, 'years'),
            'baptised' => self::stringValue($data, 'baptised'),
            'story' => self::stringValue($data, 'story'),
            'why' => self::stringValue($data, 'why'),
            'interest' => self::stringValue($data, 'interest'),
            'interest2' => self::stringValue($data, 'interest2'),
            'attendance_commitment' => self::boolLabel($data, 'attendance_commitment'),
            'conduct_commitment' => self::boolLabel($data, 'conduct_commitment'),
            'communication_commitment' => self::boolLabel($data, 'communication_commitment'),
            'declaration_signature' => self::stringValue($data, 'declaration_signature'),
            'declaration_date' => self::stringValue($data, 'declaration_date'),
            'declaration_confirmed' => self::boolLabel($data, 'declaration_confirmed'),
            'guardian_name' => self::stringValue($data, 'guardian_name'),
            'guardian_relationship' => self::stringValue($data, 'guardian_relationship'),
            'guardian_phone' => self::stringValue($data, 'guardian_phone'),
            'guardian_email' => self::stringValue($data, 'guardian_email'),
            'guardian_consent' => self::boolLabel($data, 'guardian_consent'),
            'recommender_name' => self::stringValue($data, 'recommender_name'),
            'recommender_position' => self::stringValue($data, 'recommender_position'),
            'recommender_phone' => self::stringValue($data, 'recommender_phone'),
            'recommender_email' => self::stringValue($data, 'recommender_email'),
        ];

        foreach ($data as $key => $value) {
            if (! is_string($key) || array_key_exists($key, $values)) {
                continue;
            }
            if (self::isHiddenApplicationKey($key)) {
                continue;
            }
            $text = self::scalarText($value);
            $bare = self::bareStepKey($key);
            if ($bare !== null) {
                if (self::isHiddenApplicationKey($bare) || ($values[$bare] ?? '') !== '' || array_key_exists($bare, $data)) {
                    continue;
                }
                $key = $bare;
            }
            if ($text !== '') {
                $values[$key] = $text;
            }
        }

        return array_filter($values, static fn (string $value): bool => $value !== '');
    }

    /**
     * @return list<array{title: string, fields: list<array{key: string, label: string, value: string}>}>
     */
    public static function sections(?KcaApplication $application, ?Person $person = null): array
    {
        $flat = self::flattened($application, $person);
        $used = [];
        $sections = [];

        foreach (self::SECTIONS as $title => $keys) {
            $fields = [];
            foreach ($keys as $key) {
                if (! isset($flat[$key])) {
                    continue;
                }
                $used[$key] = true;
                $fields[] = [
                    'key' => $key,
                    'label' => self::FIELD_LABELS[$key] ?? self::humanize($key),
                    'value' => $flat[$key],
                ];
            }
            if ($fields !== []) {
                $sections[] = ['title' => $title, 'fields' => $fields];
            }
        }

        $extra = [];
        foreach ($flat as $key => $value) {
            if (isset($used[$key])) {
                continue;
            }
            $extra[] = [
                'key' => $key,
                'label' => self::FIELD_LABELS[$key] ?? self::humanize($key),
                'value' => $value,
            ];
        }
        if ($extra !== []) {
            $sections[] = ['title' => 'Additional application answers', 'fields' => $extra];
        }

        return $sections;
    }

    /**
     * Drop wizard copies such as step1_church_ministry when church_ministry is already stored.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function normalizeIncoming(array $incoming): array
    {
        $normalized = [];
        foreach ($incoming as $key => $value) {
            if (! is_string($key) || in_array($key, ['password', 'password_confirmation'], true)) {
                continue;
            }
            $bare = self::bareStepKey($key);
            if ($bare !== null) {
                if (! in_array($bare, ['password', 'password_confirmation'], true) && ! array_key_exists($bare, $incoming) && ! array_key_exists($bare, $normalized)) {
                    $normalized[$bare] = $value;
                }
                continue;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private static function isHiddenApplicationKey(string $key): bool
    {
        return in_array($key, [
            'church_id',
            'home_church_id',
            'pastor_id',
            'person_id',
            'password',
            'password_confirmation',
            'choice_index',
            'checked_indexes',
            'interest_indexes',
            'source',
            'channel',
            'steps_completed',
            'submitted_at',
        ], true);
    }

    private static function bareStepKey(string $key): ?string
    {
        if (preg_match('/\Astep\d+_(.+)\z/', $key, $matches) !== 1) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : null;
    }

    /** @param  array<string, mixed>  $data */
    private static function stringValue(array $data, string $key): string
    {
        return self::scalarText($data[$key] ?? null);
    }

    /** @param  array<string, mixed>  $data */
    private static function boolLabel(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes' || $value === 'on') {
            return 'Yes';
        }
        if ($value === false || $value === 0 || $value === '0' || $value === 'false' || $value === 'no') {
            return 'No';
        }

        return self::scalarText($value);
    }

    private static function scalarText(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private static function humanize(string $key): string
    {
        return ucfirst(trim(str_replace(['_', '-'], ' ', $key)));
    }
}
