<?php

namespace App\Support\Kca;

use App\Models\KcaEnrollment;
use App\Models\KcaYear;

final class GenerateKcaRegistrationNumberAction
{
    public function handle(?KcaYear $year = null): string
    {
        $year ??= KcaYear::query()->orderByDesc('starts_on')->first();
        $yearLabel = $this->yearLabel($year);
        $count = KcaEnrollment::query()
            ->when(
                $year !== null,
                static fn ($query) => $query->where('kca_year_id', $year->getKey()),
                static fn ($query) => $query->whereYear('created_at', (int) $yearLabel),
            )
            ->count() + 1;

        return sprintf('KCA-%s-%05d', $yearLabel, $count);
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
