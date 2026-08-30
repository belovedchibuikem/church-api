<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreateKcaCohortRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaLecturerAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaLessonRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaMentorAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaModuleRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaYearRequest;
use App\Http\Requests\Api\V1\Admin\EnrollKcaStudentRequest;
use App\Http\Requests\Api\V1\Admin\IssueKcaCertificateRequest;
use App\Http\Requests\Api\V1\Admin\RecordKcaAttendanceRequest;
use App\Http\Requests\Api\V1\Admin\RevokeKcaCertificateRequest;
use App\Http\Requests\Api\V1\Admin\ReviewKcaEvidenceRequest;
use App\Http\Requests\Api\V1\Admin\SubmitKcaEvidenceRequest;
use App\Http\Requests\Api\V1\Admin\TransitionKcaApplicationRequest;
use App\Http\Requests\Api\V1\Admin\TransitionKcaAssignmentRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Kca\KcaApplicationState;
use App\Kca\KcaAssignmentState;
use App\Kca\KcaAttendanceStatus;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaCertificate;
use App\Models\KcaCertificateRevocation;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaLesson;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\Person;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Kca\CreateKcaCohortAction;
use App\Support\Kca\CreateKcaLecturerAssignmentAction;
use App\Support\Kca\CreateKcaLessonAction;
use App\Support\Kca\CreateKcaMentorAssignmentAction;
use App\Support\Kca\CreateKcaModuleAction;
use App\Support\Kca\CreateKcaYearAction;
use App\Support\Kca\EnrollKcaStudentAction;
use App\Support\Kca\IssueKcaCertificateAction;
use App\Support\Kca\RecordKcaAttendanceAction;
use App\Support\Kca\RevokeKcaCertificateAction;
use App\Support\Kca\ReviewKcaEvidenceAction;
use App\Support\Kca\SubmitKcaEvidenceAction;
use App\Support\Kca\TransitionKcaApplicationAction;
use App\Support\Kca\TransitionKcaAssignmentAction;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KcaOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function transitionApplication(TransitionKcaApplicationRequest $request, string $application, TransitionKcaApplicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaApplication::query()->where('public_id', $application)->firstOrFail();
        $updated = $this->execute(fn (): KcaApplication => $action->handle(
            $target,
            KcaApplicationState::from((string) $request->validated('status')),
            $context->actor($request),
            $request->validated('reason_code'),
        ));
        $updated->load(['person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function enroll(EnrollKcaStudentRequest $request, string $application, EnrollKcaStudentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaApplication::query()->where('public_id', $application)->firstOrFail();
        $cohort = KcaCohort::query()->where('public_id', $request->validated('cohort_id'))->firstOrFail();
        $enrollment = $this->execute(fn (): KcaEnrollment => $action->handle(
            $target,
            $cohort,
            (string) $request->validated('registration_number'),
            CarbonImmutable::parse((string) $request->validated('starts_on')),
            $context->actor($request),
        ));
        $enrollment->load(['application:id,public_id', 'person:id,public_id', 'year:id,public_id', 'cohort:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($enrollment))->resolve($request), status: 201);
    }

    public function transitionAssignment(TransitionKcaAssignmentRequest $request, string $assignment, TransitionKcaAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $updated = $this->execute(fn (): KcaAssignment => $action->handle(
            $target,
            KcaAssignmentState::from((string) $request->validated('status')),
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function submitEvidence(SubmitKcaEvidenceRequest $request, string $assignment, SubmitKcaEvidenceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $enrollment = KcaEnrollment::query()->where('public_id', $request->validated('enrollment_id'))->firstOrFail();
        $fileAsset = FileAsset::query()->where('public_id', $request->validated('file_asset_id'))->firstOrFail();
        $submittedBy = Person::query()->where('public_id', $request->validated('submitted_by_person_id'))->firstOrFail();
        $evidence = $this->execute(fn (): KcaEvidenceSubmission => $action->handle(
            $target,
            $enrollment,
            $fileAsset,
            $submittedBy,
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
        ));
        $evidence->load(['enrollment:id,public_id', 'fileAsset:id,public_id', 'submittedBy:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($evidence))->resolve($request), status: 201);
    }

    public function reviewEvidence(ReviewKcaEvidenceRequest $request, string $evidence, ReviewKcaEvidenceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaEvidenceSubmission::query()->where('public_id', $evidence)->firstOrFail();
        $reviewer = Person::query()->where('public_id', $request->validated('reviewer_person_id'))->firstOrFail();
        $review = $this->execute(fn (): KcaEvidenceReview => $action->handle(
            $target,
            $reviewer,
            KcaAssignmentState::from((string) $request->validated('outcome')),
            $context->actor($request),
        ));
        $review->load(['evidenceSubmission:id,public_id', 'reviewer:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($review))->resolve($request), status: 201);
    }

    public function issueCertificate(IssueKcaCertificateRequest $request, string $enrollment, IssueKcaCertificateAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaEnrollment::query()->where('public_id', $enrollment)->firstOrFail();
        $certificate = $this->execute(fn (): KcaCertificate => $action->handle(
            $target,
            (string) $request->validated('certificate_number'),
            CarbonImmutable::parse((string) $request->validated('completion_on')),
            (string) $request->validated('verification_code'),
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
        ));
        $certificate->load(['enrollment:id,public_id', 'person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($certificate))->resolve($request), status: 201);
    }

    public function revokeCertificate(RevokeKcaCertificateRequest $request, string $certificate, RevokeKcaCertificateAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaCertificate::query()->where('public_id', $certificate)->firstOrFail();
        $revocation = $this->execute(fn (): KcaCertificateRevocation => $action->handle(
            $target,
            (string) $request->validated('reason_code'),
            $request->validated('notes'),
            $context->actor($request),
        ));

        return ApiResponse::success($request, [
            'id' => $revocation->public_id,
            'certificate_id' => $target->public_id,
            'reason_code' => $revocation->reason_code,
            'revoked_at' => $revocation->revoked_at?->utc()->toIso8601String(),
        ], status: 201);
    }

    public function storeYear(CreateKcaYearRequest $request, CreateKcaYearAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $year = $this->execute(fn (): KcaYear => $action->handle(
            (string) $request->validated('code'),
            (string) $request->validated('name'),
            CarbonImmutable::parse((string) $request->validated('starts_on')),
            CarbonImmutable::parse((string) $request->validated('ends_on')),
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($year))->resolve($request), status: 201);
    }

    public function storeCohort(CreateKcaCohortRequest $request, string $year, CreateKcaCohortAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaYear::query()->where('public_id', $year)->firstOrFail();
        $cohort = $this->execute(fn (): KcaCohort => $action->handle(
            $target,
            (string) $request->validated('code'),
            (string) $request->validated('name'),
            CarbonImmutable::parse((string) $request->validated('starts_on')),
            CarbonImmutable::parse((string) $request->validated('ends_on')),
            $context->actor($request),
        ));
        $cohort->load(['year:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($cohort))->resolve($request), status: 201);
    }

    public function storeModule(CreateKcaModuleRequest $request, CreateKcaModuleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $module = $this->execute(fn (): KcaModule => $action->handle(
            (string) $request->validated('code'),
            (string) $request->validated('title'),
            (int) $request->validated('sequence'),
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($module))->resolve($request), status: 201);
    }

    public function storeLesson(CreateKcaLessonRequest $request, string $module, CreateKcaLessonAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaModule::query()->where('public_id', $module)->firstOrFail();
        $lesson = $this->execute(fn (): KcaLesson => $action->handle(
            $target,
            (string) $request->validated('code'),
            (string) $request->validated('title'),
            (int) $request->validated('sequence'),
            $context->actor($request),
        ));
        $lesson->load(['module:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($lesson))->resolve($request), status: 201);
    }

    public function recordAttendance(RecordKcaAttendanceRequest $request, string $enrollment, RecordKcaAttendanceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaEnrollment::query()->where('public_id', $enrollment)->firstOrFail();
        $lesson = KcaLesson::query()->where('public_id', $request->validated('lesson_id'))->firstOrFail();
        $attendance = $this->execute(fn (): KcaAttendance => $action->handle(
            $target,
            $lesson,
            KcaAttendanceStatus::from((string) $request->validated('status')),
            CarbonImmutable::parse((string) $request->validated('session_on')),
            $context->actor($request),
        ));
        $attendance->load(['enrollment:id,public_id', 'lesson:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($attendance))->resolve($request), status: 201);
    }

    public function storeLecturerAssignment(CreateKcaLecturerAssignmentRequest $request, CreateKcaLecturerAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $assignment = $this->execute(fn (): KcaLecturerAssignment => $action->handle(
            KcaModule::query()->where('public_id', $request->validated('kca_module_id'))->firstOrFail(),
            KcaCohort::query()->where('public_id', $request->validated('kca_cohort_id'))->firstOrFail(),
            Person::query()->where('public_id', $request->validated('lecturer_person_id'))->firstOrFail(),
            CarbonImmutable::parse((string) $request->validated('starts_at')),
            $request->validated('ends_at') === null ? null : CarbonImmutable::parse((string) $request->validated('ends_at')),
            $context->actor($request),
        ));
        $assignment->load([
            'module:id,public_id,title,code',
            'cohort:id,public_id,name,code',
            ...PersonDisplayName::eager('lecturer'),
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($assignment))->resolve($request), status: 201);
    }

    public function destroyLecturerAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaLecturerAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $this->execute(function () use ($target): true {
            $target->delete();

            return true;
        });

        return ApiResponse::success($request, ['id' => $assignment, 'deleted' => true]);
    }

    public function storeMentorAssignment(CreateKcaMentorAssignmentRequest $request, CreateKcaMentorAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $assignment = $this->execute(fn (): KcaMentorAssignment => $action->handle(
            KcaEnrollment::query()->where('public_id', $request->validated('kca_enrollment_id'))->firstOrFail(),
            Person::query()->where('public_id', $request->validated('mentor_person_id'))->firstOrFail(),
            CarbonImmutable::parse((string) $request->validated('starts_at')),
            $request->validated('ends_at') === null ? null : CarbonImmutable::parse((string) $request->validated('ends_at')),
            $context->actor($request),
        ));
        $assignment->load([
            'enrollment:id,public_id',
            ...PersonDisplayName::eager('mentor'),
            ...PersonDisplayName::eager('enrollment.person'),
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($assignment))->resolve($request), status: 201);
    }

    public function destroyMentorAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaMentorAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $this->execute(function () use ($target): true {
            $target->delete();

            return true;
        });

        return ApiResponse::success($request, ['id' => $assignment, 'deleted' => true]);
    }
}
