<?php

namespace App\Support\Kca;

use App\Models\KcaEnrollment;
use App\Models\KcaMentorAssignment;

final class KcaCurriculumMentorResolver
{
    /** @return array<string, mixed>|null */
    public function current(KcaEnrollment $enrollment): ?array
    {
        $assignment = KcaMentorAssignment::query()
            ->with('mentor.profile')
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        if ($assignment?->mentor === null) {
            return null;
        }

        $profile = $assignment->mentor->profile;

        return [
            'assignment_id' => $assignment->public_id,
            'person_id' => $assignment->mentor->public_id,
            'given_name' => $profile?->given_name,
            'family_name' => $profile?->family_name,
            'preferred_name' => $profile?->preferred_name,
            'starts_at' => $assignment->starts_at?->toIso8601String(),
            'ends_at' => $assignment->ends_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>|null  $mentor */
    public function displayName(?array $mentor): ?string
    {
        if ($mentor === null) {
            return null;
        }
        $preferred = trim((string) ($mentor['preferred_name'] ?? ''));
        if ($preferred !== '') {
            return $preferred;
        }
        $name = trim(trim((string) ($mentor['given_name'] ?? '')).' '.trim((string) ($mentor['family_name'] ?? '')));

        return $name === '' ? null : $name;
    }
}
