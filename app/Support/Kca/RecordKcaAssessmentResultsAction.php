<?php

namespace App\Support\Kca;

use App\Models\KcaAssessmentResult;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordKcaAssessmentResultsAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @return array{recorded: int, assessment_code: string}
     */
    public function handle(
        string $audience,
        ?KcaEnrollment $enrollment,
        ?KcaYear $year,
        ?KcaModule $module,
        ?KcaLesson $lesson,
        string $assessmentCode,
        string $resultCode,
        ?string $score,
        User $actor,
    ): array {
        $code = Str::squish($assessmentCode);
        $result = Str::squish($resultCode);
        if ($code === '') {
            throw new InvalidArgumentException('An assessment name is required.');
        }
        if ($lesson !== null) {
            $code = $code.' · '.$lesson->code;
        }

        $enrollments = $this->resolveEnrollments($audience, $enrollment, $year);
        if ($enrollments->isEmpty()) {
            throw new InvalidArgumentException('No enrolled students match the selected audience.');
        }

        $recorded = 0;
        DB::transaction(function () use ($enrollments, $module, $code, $result, $score, $actor, &$recorded): void {
            foreach ($enrollments as $target) {
                $locked = KcaEnrollment::query()->lockForUpdate()->findOrFail($target->getKey());
                $attempt = (int) KcaAssessmentResult::query()
                    ->whereBelongsTo($locked, 'enrollment')
                    ->where('assessment_code', $code)
                    ->max('attempt_number');

                $row = KcaAssessmentResult::query()->create([
                    'kca_enrollment_id' => $locked->getKey(),
                    'kca_module_id' => $module?->getKey(),
                    'assessment_code' => $code,
                    'result_code' => $result,
                    'score' => $score,
                    'attempt_number' => $attempt + 1,
                    'assessed_by_user_id' => $actor->getKey(),
                    'assessed_at' => now()->utc(),
                ]);

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'kca.assessment.recorded',
                    actor: $actor,
                    targetType: 'kca_assessment_result',
                    targetId: $row->public_id,
                    metadata: [
                        'enrollment_id' => $locked->public_id,
                        'assessment_code' => $code,
                        'result_code' => $result,
                    ],
                ));
                $recorded++;
            }
        }, attempts: 3);

        return ['recorded' => $recorded, 'assessment_code' => $code];
    }

    /**
     * @return Collection<int, KcaEnrollment>
     */
    private function resolveEnrollments(string $audience, ?KcaEnrollment $enrollment, ?KcaYear $year): Collection
    {
        $normalized = strtolower($audience);
        if ($enrollment !== null && (str_contains($normalized, 'one') || $normalized === 'student')) {
            return collect([$enrollment]);
        }
        $query = KcaEnrollment::query()->orderBy('id');
        if ($year !== null && str_contains($normalized, 'year')) {
            $query->whereBelongsTo($year, 'year');
        }

        return $query->get();
    }
}
