<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreateAdminKcaApplicationRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaChapterRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaCohortRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaStudentAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateKcaStudentAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaLecturerAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaLessonRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaMentorAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaModulePrerequisiteRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaModuleRequest;
use App\Http\Requests\Api\V1\Admin\CreateKcaYearRequest;
use App\Http\Requests\Api\V1\Admin\EnrollKcaStudentRequest;
use App\Http\Requests\Api\V1\Admin\ImportKcaStudentsRequest;
use App\Http\Requests\Api\V1\Admin\IssueKcaCertificateRequest;
use App\Http\Requests\Api\V1\Admin\RecordKcaAssessmentResultsRequest;
use App\Http\Requests\Api\V1\Admin\RecordKcaAttendanceRequest;
use App\Http\Requests\Api\V1\Admin\RecordKcaMassAttendanceRequest;
use App\Http\Requests\Api\V1\Admin\ReviewKcaEvidenceRequest;
use App\Http\Requests\Api\V1\Admin\RevokeKcaCertificateRequest;
use App\Http\Requests\Api\V1\Admin\SubmitKcaEvidenceRequest;
use App\Http\Requests\Api\V1\Admin\TransitionKcaApplicationRequest;
use App\Http\Requests\Api\V1\Admin\TransitionKcaAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateKcaCohortRequest;
use App\Http\Requests\Api\V1\Admin\UpdateKcaLessonRequest;
use App\Http\Requests\Api\V1\Admin\UpdateKcaModuleRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Kca\KcaApplicationState;
use App\Kca\KcaAssignmentState;
use App\Kca\KcaAttendanceStatus;
use App\Models\FileAsset;
use App\Models\KcaChapter;
use App\Models\KcaApplication;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaCertificate;
use App\Models\KcaCertificateRevocation;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaLeadershipRecommendation;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaLesson;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\KcaModulePrerequisite;
use App\Models\KcaYear;
use App\Models\Person;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Identity\PersonDisplayName;
use App\Support\Kca\CompleteKcaOrientationAction;
use App\Support\Kca\CreateAdminKcaApplicationAction;
use App\Support\Kca\CreateKcaAssignmentAction;
use App\Support\Kca\DeleteKcaAssignmentAction;
use App\Support\Kca\UpdateKcaAssignmentAction;
use App\Support\Kca\CreateKcaChapterAction;
use App\Support\Kca\CreateKcaCohortAction;
use App\Support\Kca\CreateKcaLecturerAssignmentAction;
use App\Support\Kca\CreateKcaLessonAction;
use App\Support\Kca\CreateKcaMentorAssignmentAction;
use App\Support\Kca\CreateKcaModuleAction;
use App\Support\Kca\CreateKcaYearAction;
use App\Support\Kca\EnrollKcaStudentAction;
use App\Support\Kca\ExportKcaStudentsAction;
use App\Support\Kca\GenerateKcaRegistrationNumberAction;
use App\Support\Kca\ImportKcaStudentsAction;
use App\Support\Kca\IssueKcaCertificateAction;
use App\Support\Kca\MapKcaModuleDaysAction;
use App\Support\Kca\PublishKcaModuleAction;
use App\Support\Kca\RecordKcaAssessmentResultsAction;
use App\Support\Kca\RecordKcaAttendanceAction;
use App\Support\Kca\RecordKcaMassAttendanceAction;
use App\Support\Kca\ReviewKcaEvidenceAction;
use App\Support\Kca\RevokeKcaCertificateAction;
use App\Support\Kca\SubmitKcaEvidenceAction;
use App\Support\Kca\TransitionKcaApplicationToStatusAction;
use App\Support\Kca\TransitionKcaAssignmentAction;
use App\Support\Kca\UpdateKcaLessonAction;
use App\Support\Kca\UpdateKcaModuleAction;
use App\Support\Kca\VerifyKcaLeadershipRecommendationAction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KcaOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function storeApplication(CreateAdminKcaApplicationRequest $request, CreateAdminKcaApplicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $validated = $request->validated();
        $existingApplication = isset($validated['application_id'])
            ? KcaApplication::query()->where('public_id', $validated['application_id'])->firstOrFail()
            : null;
        $person = isset($validated['person_id'])
            ? Person::query()->where('public_id', $validated['person_id'])->firstOrFail()
            : null;
        $application = $this->execute(fn (): KcaApplication => $action->handle(
            $existingApplication,
            $person,
            $validated['application_data'],
            $context->actor($request),
            $validated['given_name'] ?? null,
            $validated['family_name'] ?? null,
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
            (bool) ($validated['finalize'] ?? true),
            (bool) ($validated['create_login'] ?? false),
            $validated['password'] ?? null,
        ));
        $application->load(['person:id,public_id', 'person.profile']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($application))->resolve($request), status: 201);
    }

    public function transitionApplication(TransitionKcaApplicationRequest $request, string $application, TransitionKcaApplicationToStatusAction $action, ProtectedAdminContext $context): JsonResponse
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

    public function completeOrientation(Request $request, string $application, CompleteKcaOrientationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaApplication::query()->where('public_id', $application)->firstOrFail();
        $updated = $this->execute(fn (): KcaApplication => $action->handleForAdmin(
            $target,
            $context->actor($request),
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
            $request->validated('registration_number'),
            CarbonImmutable::parse((string) $request->validated('starts_on')),
            $context->actor($request),
        ));
        $enrollment->load(['application:id,public_id', 'person:id,public_id', 'year:id,public_id', 'cohort:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($enrollment))->resolve($request), status: 201);
    }

    public function downloadStudentImportTemplate(Request $request, ExportKcaStudentsAction $action, ProtectedAdminContext $context): StreamedResponse
    {
        $context->ensureGlobal($request);

        return $action->template();
    }

    public function exportStudents(Request $request, ExportKcaStudentsAction $action, ProtectedAdminContext $context): StreamedResponse
    {
        $context->ensureGlobal($request);

        return $action->enrolledStudents();
    }

    public function importStudents(ImportKcaStudentsRequest $request, ImportKcaStudentsAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $result = $this->execute(fn (): array => $action->handle(
            $request->file('file'),
            $context->actor($request),
            (string) $request->validated('mode', 'enroll'),
        ));

        return ApiResponse::success($request, $result, status: 201);
    }

    public function previewRegistrationNumber(
        Request $request,
        GenerateKcaRegistrationNumberAction $registrationNumbers,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $validated = $request->validate([
            'cohort_id' => ['nullable', 'ulid', 'exists:kca_cohorts,public_id'],
        ]);

        $year = null;
        if (isset($validated['cohort_id'])) {
            $cohort = KcaCohort::query()
                ->with('year:id,public_id,code,name,starts_on')
                ->where('public_id', $validated['cohort_id'])
                ->firstOrFail();
            $year = $cohort->year;
        }

        return ApiResponse::success($request, [
            'registration_number' => $registrationNumbers->handle($year),
        ]);
    }

    public function verifyRecommendation(Request $request, string $recommendation, VerifyKcaLeadershipRecommendationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaLeadershipRecommendation::query()->where('public_id', $recommendation)->firstOrFail();
        $updated = $action->handle($target, $context->actor($request));
        $updated->load(['application:id,public_id']);

        return ApiResponse::success($request, [
            'id' => $updated->public_id,
            'application_id' => $updated->application?->public_id,
            'status' => $updated->status,
            'recommender_name' => $updated->recommender_name,
            'recommender_email' => $updated->recommender_email,
            'submitted_at' => $updated->submitted_at?->utc()->toIso8601String(),
            'verified_at' => $updated->verified_at?->utc()->toIso8601String(),
        ]);
    }

    public function transitionAssignment(TransitionKcaAssignmentRequest $request, string $assignment, TransitionKcaAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $updated = $this->execute(fn (): KcaAssignment => $action->handle(
            $target,
            KcaAssignmentState::fromStored((string) $request->validated('status')),
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
            (string) ($request->validated('timezone') ?? 'UTC'),
        ));
        $cohort->load(['year:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($cohort))->resolve($request), status: 201);
    }

    public function updateCohort(UpdateKcaCohortRequest $request, string $cohort, UpdateKcaCohortAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaCohort::query()->where('public_id', $cohort)->firstOrFail();
        $year = $request->filled('year_id')
            ? KcaYear::query()->where('public_id', $request->validated('year_id'))->firstOrFail()
            : null;
        $updated = $this->execute(fn (): KcaCohort => $action->handle(
            $target,
            (string) $request->validated('code'),
            (string) $request->validated('name'),
            CarbonImmutable::parse((string) $request->validated('starts_on')),
            CarbonImmutable::parse((string) $request->validated('ends_on')),
            $context->actor($request),
            $year,
            $request->has('timezone') ? (string) ($request->validated('timezone') ?? 'UTC') : null,
        ));
        $updated->load(['year:id,public_id,name,code']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function storeModule(CreateKcaModuleRequest $request, CreateKcaModuleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $module = $this->execute(fn (): KcaModule => $action->handle(
            (string) $request->validated('code'),
            (string) $request->validated('title'),
            (int) $request->validated('sequence'),
            (int) $request->validated('duration_days'),
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($module))->resolve($request), status: 201);
    }

    public function updateModule(UpdateKcaModuleRequest $request, string $module, UpdateKcaModuleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaModule::query()->where('public_id', $module)->firstOrFail();
        $updated = $this->execute(fn (): KcaModule => $action->handle(
            $target,
            (string) $request->validated('code'),
            (string) $request->validated('title'),
            (int) $request->validated('sequence'),
            (int) $request->validated('duration_days'),
            $request->boolean('is_active', true),
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function mapModuleDays(Request $request, string $module, MapKcaModuleDaysAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $data = $request->validate([
            'day_indexes' => ['nullable', 'array'],
            'day_indexes.*' => ['integer', 'min:1', 'max:365'],
        ]);
        $target = KcaModule::query()->where('public_id', $module)->firstOrFail();
        $updated = $this->execute(fn (): KcaModule => $action->handle(
            $target,
            isset($data['day_indexes']) ? array_map('intval', $data['day_indexes']) : null,
            $context->actor($request),
        ));
        $updated->load(['lessons']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function publishModule(Request $request, string $module, PublishKcaModuleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaModule::query()->where('public_id', $module)->firstOrFail();
        $updated = $this->execute(fn (): KcaModule => $action->handle($target, $context->actor($request)));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
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
            [
                'summary' => $request->validated('summary'),
                'body' => $request->validated('body'),
                'content_url' => $request->validated('content_url'),
                'estimated_minutes' => $request->validated('estimated_minutes'),
                'lesson_type' => $request->validated('lesson_type') ?? 'text',
                'day_index' => $request->validated('day_index'),
                'requires_acknowledgement' => $request->boolean('requires_acknowledgement', true),
                'chapters' => $request->validated('chapters') ?? [],
            ],
        ));
        $lesson->load(['module:id,public_id', 'chapters']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($lesson))->resolve($request), status: 201);
    }

    public function updateLesson(UpdateKcaLessonRequest $request, string $lesson, UpdateKcaLessonAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaLesson::query()->where('public_id', $lesson)->firstOrFail();
        $updated = $this->execute(fn (): KcaLesson => $action->handle(
            $target,
            (string) $request->validated('code'),
            (string) $request->validated('title'),
            (int) $request->validated('sequence'),
            $context->actor($request),
            [
                'summary' => $request->validated('summary'),
                'body' => $request->validated('body'),
                'content_url' => $request->validated('content_url'),
                'lesson_type' => $request->validated('lesson_type'),
                'day_index' => $request->validated('day_index'),
            ],
        ));
        $updated->load(['module:id,public_id,title,code', 'chapters']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function storeChapter(CreateKcaChapterRequest $request, string $lesson, CreateKcaChapterAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaLesson::query()->where('public_id', $lesson)->firstOrFail();
        $chapter = $this->execute(fn (): KcaChapter => $action->handle(
            $target,
            (string) $request->validated('code'),
            (string) $request->validated('title'),
            (int) $request->validated('sequence'),
            $context->actor($request),
            [
                'summary' => $request->validated('summary'),
                'body' => $request->validated('body'),
                'content_url' => $request->validated('content_url'),
                'estimated_minutes' => $request->validated('estimated_minutes'),
            ],
        ));
        $chapter->load(['lesson:id,public_id,title,code']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($chapter))->resolve($request), status: 201);
    }

    public function storeAssignment(CreateKcaStudentAssignmentRequest $request, CreateKcaAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $validated = $request->validated();
        $enrollment = isset($validated['kca_enrollment_id'])
            ? KcaEnrollment::query()->where('public_id', $validated['kca_enrollment_id'])->firstOrFail()
            : null;
        $cohort = isset($validated['cohort_id'])
            ? KcaCohort::query()->where('public_id', $validated['cohort_id'])->firstOrFail()
            : null;
        $module = KcaModule::query()->where('public_id', $validated['kca_module_id'])->firstOrFail();
        $lesson = KcaLesson::query()->where('public_id', $validated['kca_lesson_id'])->firstOrFail();
        $dueAt = isset($validated['due_at'])
            ? CarbonImmutable::parse((string) $validated['due_at'])
            : null;
        $asDraft = filter_var($validated['as_draft'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $result = $this->execute(fn (): array => $action->handleForAudience(
            (string) ($validated['audience'] ?? 'student'),
            $enrollment,
            $cohort,
            $module,
            $lesson,
            (string) $validated['title'],
            $context->actor($request),
            (string) ($validated['assignment_kind'] ?? 'standard'),
            array_map('intval', $validated['soul_tree_levels'] ?? []),
            $dueAt,
            $asDraft ? KcaAssignmentState::Draft : KcaAssignmentState::Assigned,
        ));

        $first = $result['assignments'][0] ?? null;
        if ($first instanceof KcaAssignment) {
            $first->load($this->assignmentCatalogRelations());
        }

        if ($result['created'] === 1 && $first instanceof KcaAssignment) {
            return ApiResponse::success(
                $request,
                (new ProtectedCatalogRecordResource($first))->resolve($request),
                status: 201,
            );
        }

        return ApiResponse::success($request, [
            'id' => $first?->public_id,
            'created' => $result['created'],
            'audience' => $result['audience'],
            'assignment_ids' => array_map(
                static fn (KcaAssignment $assignment): string => $assignment->public_id,
                $result['assignments'],
            ),
            'first' => $first instanceof KcaAssignment
                ? (new ProtectedCatalogRecordResource($first))->resolve($request)
                : null,
        ], status: 201);
    }

    public function showAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $target->load($this->assignmentCatalogRelations());

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($target))->resolve($request));
    }

    public function updateAssignment(UpdateKcaStudentAssignmentRequest $request, string $assignment, UpdateKcaAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $validated = $request->validated();
        $clearDueAt = array_key_exists('due_at', $validated) && $validated['due_at'] === null;
        $dueAt = ! $clearDueAt && ! empty($validated['due_at'])
            ? CarbonImmutable::parse((string) $validated['due_at'])
            : null;
        $module = isset($validated['kca_module_id'])
            ? KcaModule::query()->where('public_id', $validated['kca_module_id'])->firstOrFail()
            : null;
        $lesson = isset($validated['kca_lesson_id'])
            ? KcaLesson::query()->where('public_id', $validated['kca_lesson_id'])->firstOrFail()
            : null;
        $updated = $this->execute(fn (): KcaAssignment => $action->handle(
            $target,
            $context->actor($request),
            isset($validated['title']) ? (string) $validated['title'] : null,
            $dueAt,
            $clearDueAt,
            array_key_exists('soul_tree_levels', $validated)
                ? array_map('intval', $validated['soul_tree_levels'] ?? [])
                : null,
            $module,
            $lesson,
            isset($validated['assignment_kind']) ? (string) $validated['assignment_kind'] : null,
        ));
        $updated->load($this->assignmentCatalogRelations());

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function destroyAssignment(Request $request, string $assignment, DeleteKcaAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $this->execute(function () use ($action, $target, $context, $request): true {
            $action->handle($target, $context->actor($request));

            return true;
        });

        return ApiResponse::success($request, ['id' => $assignment, 'deleted' => true]);
    }

    public function storePrerequisite(CreateKcaModulePrerequisiteRequest $request, string $module, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaModule::query()->where('public_id', $module)->firstOrFail();
        $prerequisiteModule = KcaModule::query()->where('public_id', $request->validated('prerequisite_module_id'))->firstOrFail();
        if ($target->is($prerequisiteModule)) {
            throw ValidationException::withMessages([
                'prerequisite_module_id' => ['A module cannot be a prerequisite of itself.'],
            ]);
        }
        $prerequisite = $this->execute(fn (): KcaModulePrerequisite => KcaModulePrerequisite::query()->create([
            'kca_module_id' => $target->getKey(),
            'prerequisite_module_id' => $prerequisiteModule->getKey(),
            'requirement' => (string) $request->validated('requirement'),
        ]));
        $prerequisite->load([
            'module:id,public_id,title,code',
            'prerequisiteModule:id,public_id,title,code',
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($prerequisite))->resolve($request), status: 201);
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
        $attendance->load(['enrollment:id,public_id,registration_number', 'lesson:id,public_id,title,code', ...PersonDisplayName::eager('enrollment.person')]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($attendance))->resolve($request), status: 201);
    }

    public function attendanceRoster(Request $request, RecordKcaMassAttendanceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $data = $request->validate([
            'lesson_id' => ['required', 'ulid', 'exists:kca_lessons,public_id'],
            'session_on' => ['required', 'date'],
            'cohort_id' => ['nullable', 'ulid', 'exists:kca_cohorts,public_id'],
        ]);
        $lesson = KcaLesson::query()->where('public_id', $data['lesson_id'])->firstOrFail();
        $sessionOn = CarbonImmutable::parse((string) $data['session_on']);
        $roster = $action->roster($lesson, $sessionOn, $data['cohort_id'] ?? null);

        return ApiResponse::success($request, [
            'lesson' => [
                'id' => $lesson->public_id,
                'title' => $lesson->title,
                'code' => $lesson->code,
            ],
            'session_on' => $sessionOn->toDateString(),
            'students' => $roster->all(),
            'total' => $roster->count(),
            'marked' => $roster->whereNotNull('status')->count(),
        ]);
    }

    public function recordMassAttendance(RecordKcaMassAttendanceRequest $request, RecordKcaMassAttendanceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $validated = $request->validated();
        $lesson = KcaLesson::query()->where('public_id', $validated['lesson_id'])->firstOrFail();
        $sessionOn = CarbonImmutable::parse((string) $validated['session_on']);
        $result = $this->execute(fn (): array => $action->handle(
            $lesson,
            $sessionOn,
            array_map(static fn (array $row): array => [
                'enrollment_id' => (string) $row['enrollment_id'],
                'status' => (string) $row['status'],
            ], $validated['records']),
            $context->actor($request),
        ));

        return ApiResponse::success($request, [
            'lesson_id' => $lesson->public_id,
            'session_on' => $sessionOn->toDateString(),
            'recorded' => $result['recorded'],
            'updated' => $result['updated'],
            'total' => count($result['rows']),
            'rows' => $result['rows'],
        ], status: 201);
    }

    public function recordAssessmentResults(RecordKcaAssessmentResultsRequest $request, RecordKcaAssessmentResultsAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $validated = $request->validated();
        $enrollmentId = $validated['kca_enrollment_id'] ?? $validated['enrollment_id'] ?? null;
        $result = $this->execute(fn (): array => $action->handle(
            (string) $validated['audience'],
            $enrollmentId ? KcaEnrollment::query()->where('public_id', $enrollmentId)->firstOrFail() : null,
            isset($validated['year_id']) ? KcaYear::query()->where('public_id', $validated['year_id'])->firstOrFail() : null,
            isset($validated['kca_module_id']) ? KcaModule::query()->where('public_id', $validated['kca_module_id'])->firstOrFail() : null,
            isset($validated['kca_lesson_id']) ? KcaLesson::query()->where('public_id', $validated['kca_lesson_id'])->firstOrFail() : null,
            (string) $validated['assessment_code'],
            (string) $validated['result_code'],
            isset($validated['score']) ? (string) $validated['score'] : null,
            $context->actor($request),
        ));

        return ApiResponse::success($request, $result, status: 201);
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

    public function updateLecturerAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $target = KcaLecturerAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $this->execute(function () use ($target, $data): void {
            $target->forceFill([
                'starts_at' => CarbonImmutable::parse($data['starts_at']),
                'ends_at' => isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            ])->save();
        });
        $target->load([
            'module:id,public_id,title,code',
            'cohort:id,public_id,name,code',
            ...PersonDisplayName::eager('lecturer'),
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($target))->resolve($request));
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

    public function updateMentorAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $target = KcaMentorAssignment::query()->where('public_id', $assignment)->firstOrFail();
        $this->execute(function () use ($target, $data): void {
            $target->forceFill([
                'starts_at' => CarbonImmutable::parse($data['starts_at']),
                'ends_at' => isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            ])->save();
        });
        $target->load([
            'enrollment:id,public_id',
            ...PersonDisplayName::eager('mentor'),
            ...PersonDisplayName::eager('enrollment.person'),
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($target))->resolve($request));
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

    public function destroyPrerequisite(Request $request, string $prerequisite, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaModulePrerequisite::query()->where('public_id', $prerequisite)->firstOrFail();
        $this->execute(function () use ($target): true {
            $target->delete();

            return true;
        });

        return ApiResponse::success($request, ['id' => $prerequisite, 'deleted' => true]);
    }

    /**
     * @return list<string>
     */
    private function assignmentCatalogRelations(): array
    {
        return [
            'enrollment:id,public_id,person_id,kca_cohort_id,registration_number',
            ...PersonDisplayName::eager('enrollment.person'),
            'enrollment.cohort:id,public_id,name,code',
            'module:id,public_id,code,title',
            'lesson:id,public_id,code,title,kca_module_id',
        ];
    }
}
