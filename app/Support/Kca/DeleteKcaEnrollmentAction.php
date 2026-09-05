<?php

namespace App\Support\Kca;

use App\Models\KcaAssessmentResult;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaCertificate;
use App\Models\KcaChapterProgress;
use App\Models\KcaDevotionalReading;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaLessonProgress;
use App\Models\KcaMentorAssignment;
use App\Models\KcaSoulWin;
use App\Models\KcaStudyNote;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class DeleteKcaEnrollmentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(KcaEnrollment $enrollment, User $actor): void
    {
        DB::transaction(function () use ($enrollment, $actor): void {
            $locked = KcaEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $publicId = $locked->public_id;

            $assignmentIds = KcaAssignment::query()
                ->where('kca_enrollment_id', $locked->getKey())
                ->pluck('id');
            $evidenceIds = KcaEvidenceSubmission::query()
                ->where('kca_enrollment_id', $locked->getKey())
                ->pluck('id');

            if ($evidenceIds->isNotEmpty()) {
                KcaEvidenceReview::query()
                    ->whereIn('kca_evidence_submission_id', $evidenceIds)
                    ->delete();
            }

            if ($assignmentIds->isNotEmpty()) {
                KcaSoulWin::query()->whereIn('kca_assignment_id', $assignmentIds)->update(['parent_id' => null]);
                KcaSoulWin::query()->whereIn('kca_assignment_id', $assignmentIds)->delete();
            }

            KcaAssessmentResult::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaEvidenceSubmission::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaAssignment::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaAttendance::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaChapterProgress::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaLessonProgress::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaStudyNote::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaDevotionalReading::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaMentorAssignment::query()->where('kca_enrollment_id', $locked->getKey())->delete();
            KcaCertificate::query()->where('kca_enrollment_id', $locked->getKey())->delete();

            $locked->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.enrollment.deleted',
                actor: $actor,
                targetType: 'kca_enrollment',
                targetId: $publicId,
            ));
        }, attempts: 3);
    }
}
