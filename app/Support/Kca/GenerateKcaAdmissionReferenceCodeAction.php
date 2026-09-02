<?php

namespace App\Support\Kca;

use App\Models\KcaAdmissionLetter;
use App\Models\KcaGovernanceConfiguration;

final class GenerateKcaAdmissionReferenceCodeAction
{
    public function handle(?KcaGovernanceConfiguration $governance = null): string
    {
        $governance ??= app(ResolveKcaApplicationChurchName::class)->governanceDefaults();
        $year = now()->year;
        $count = KcaAdmissionLetter::query()->whereYear('issued_at', $year)->count() + 1;
        $prefix = trim((string) ($governance->admission_reference_prefix ?? 'KCA/ADM'));
        $prefix = $prefix !== '' ? $prefix : 'KCA/ADM';
        $prefix = rtrim($prefix, '/');

        if (preg_match('/\/\d{4}$/', $prefix) === 1) {
            return sprintf('%s/%05d', $prefix, $count);
        }

        return sprintf('%s/%d/%05d', $prefix, $year, $count);
    }
}
