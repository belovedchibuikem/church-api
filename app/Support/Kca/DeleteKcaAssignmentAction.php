<?php

namespace App\Support\Kca;

use App\Kca\KcaAssignmentState;
use App\Models\KcaAssessmentResult;
use App\Models\KcaAssignment;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaSoulWin;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteKcaAssignmentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(KcaAssignment $assignment, User $actor): void
    {
        if ($assignment->state === KcaAssignmentState::FinalAssessment) {
            throw new InvalidArgumentException('Final-assessment assignments cannot be deleted.');
        }

        DB::transaction(function () use ($assignment, $actor): void {
            $locked = KcaAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $publicId = $locked->public_id;
            $assignmentId = $locked->getKey();

            $submissionIds = KcaEvidenceSubmission::query()
                ->where('kca_assignment_id', $assignmentId)
                ->pluck('id');
            if ($submissionIds->isNotEmpty()) {
                KcaEvidenceReview::query()->whereIn('kca_evidence_submission_id', $submissionIds)->delete();
                KcaEvidenceSubmission::query()->whereIn('id', $submissionIds)->delete();
            }

            KcaSoulWin::query()
                ->where('kca_assignment_id', $assignmentId)
                ->orderByDesc('depth')
                ->get()
                ->each(fn (KcaSoulWin $win) => $win->delete());

            KcaAssessmentResult::query()
                ->where('kca_assignment_id', $assignmentId)
                ->update(['kca_assignment_id' => null]);

            $locked->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.assignment.deleted',
                actor: $actor,
                targetType: 'kca_assignment',
                targetId: $publicId,
            ));
        }, attempts: 3);
    }
}
