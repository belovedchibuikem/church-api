<?php

namespace App\Support\Kca;

use App\Models\KcaAdmissionLetter;
use App\Models\KcaEnrollment;
use App\Models\KcaYear;

final class GenerateKcaRegistrationNumberAction
{
    public function handle(?KcaYear $year = null): string
    {
        $year ??= KcaYear::query()->orderByDesc('starts_on')->first();
        $yearLabel = $this->yearLabel($year);
        $prefix = sprintf('KCA-%s-', $yearLabel);

        $enrollmentNumbers = KcaEnrollment::query()
            ->when(
                $year !== null,
                static fn ($query) => $query->where('kca_year_id', $year->getKey()),
                static fn ($query) => $query->whereYear('created_at', (int) $yearLabel),
            )
            ->pluck('registration_number');
        $reservedNumbers = KcaAdmissionLetter::query()
            ->whereNotNull('registration_number')
            ->pluck('registration_number');

        $maxSequence = $enrollmentNumbers
            ->merge($reservedNumbers)
            ->filter(static fn (mixed $value): bool => is_string($value) && str_starts_with($value, $prefix))
            ->map(static function (string $value) use ($prefix): int {
                return (int) ltrim(substr($value, strlen($prefix)), '0');
            })
            ->max();

        return sprintf('KCA-%s-%05d', $yearLabel, ((int) $maxSequence) + 1);
    }

    private function yearLabel(?KcaYear $year): string
    {
        if ($year === null) {
            return (string) now()->year;
        }

        foreach ([$year->code, $year->name] as $candidate) {
            if (preg_match('/(\d{4})/', (string) $candidate, $matches) === 1) {
                return $matches[1];
            }
        }

        return (string) $year->starts_on->year;
    }
}
