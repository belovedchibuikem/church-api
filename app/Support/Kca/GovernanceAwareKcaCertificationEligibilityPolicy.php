<?php

namespace App\Support\Kca;

use App\Kca\KcaAttendanceStatus;
use App\Models\KcaEnrollment;
use App\Models\KcaGovernanceConfiguration;
use App\Support\Kca\Contracts\KcaCertificationEligibilityPolicy;

class GovernanceAwareKcaCertificationEligibilityPolicy implements KcaCertificationEligibilityPolicy
{
    public function __construct(private readonly EnrollmentCompletionKcaCertificationEligibilityPolicy $completion) {}

    public function decide(KcaEnrollment $enrollment): KcaCertificationEligibilityDecision
    {
        $governance = KcaGovernanceConfiguration::query()->where('is_active', true)->first();
        $requireFinal = $governance?->require_final_assessment ?? true;

        if ($requireFinal) {
            $base = $this->completion->decide($enrollment);
            if (! $base->eligible) {
                return $base;
            }
        }

        if ($governance !== null) {
            $attendance = $enrollment->attendances()->get();
            if ($attendance->isNotEmpty()) {
                $present = $attendance->filter(
                    fn ($row): bool => $row->status === KcaAttendanceStatus::Present,
                )->count();
                $percent = (int) round(($present / max(1, $attendance->count())) * 100);
                if ($percent < $governance->attendance_threshold_percent) {
                    return new KcaCertificationEligibilityDecision(
                        eligible: false,
                        reasonCode: 'governance_thresholds_unmet',
                        unmetRequirements: ['attendance_threshold'],
                    );
                }
            }
        }

        return KcaCertificationEligibilityDecision::approved();
    }
}
