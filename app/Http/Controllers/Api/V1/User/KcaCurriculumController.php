<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Exceptions\KcaEvidenceOwnershipException;
use App\Exceptions\KcaEvidenceUnavailableException;
use App\Exceptions\KcaIdempotencyConflictException;
use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Kca\KcaApplicationState;
use App\Kca\KcaAssignmentState;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaChapter;
use App\Models\KcaChapterProgress;
use App\Models\KcaDevotionalReading;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaLessonProgress;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\KcaSoulWin;
use App\Models\KcaStudyNote;
use App\Models\Person;
use App\Support\Api\ApiResponse;
use App\Support\Identity\PersonDisplayName;
use App\Support\Kca\BuildKcaOrientationProgramAction;
use App\Support\Kca\CompleteKcaChapterAction;
use App\Support\Kca\CompleteKcaLessonAction;
use App\Support\Kca\CompleteKcaOrientationAction;
use App\Support\Kca\KcaCertificatePdfRenderer;
use App\Support\Kca\KcaLessonUnlockToken;
use App\Support\Kca\KcaSoulTreeService;
use App\Support\Kca\KcaStudentActivityQuery;
use App\Support\Kca\RecordKcaOrientationStageAction;
use App\Support\Kca\RecordKcaSoulWinAction;
use App\Support\Kca\SubmitKcaEvidenceAction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Member KCA curriculum: published lessons, evidence submit, certificate PDF.
 */
class KcaCurriculumController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function dashboard(Request $request, KcaStudentActivityQuery $activity): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->activeEnrollment($person);
        $isMentor = $this->menteeAssignmentsFor($person)->isNotEmpty();

        if ($enrollment === null) {
            return ApiResponse::success($request, [
                'enrolled' => false,
                'is_mentor' => $isMentor,
                'enrollment' => null,
                'modules_total' => 0,
                'modules_with_progress' => 0,
                'assignments_open' => 0,
                'assignments_due_soon' => 0,
                'attendance_recorded' => 0,
                'mentor' => null,
                'certificate' => null,
                'today_plan' => null,
                'activity' => null,
            ]);
        }

        $assignments = KcaAssignment::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereNotIn('state', [KcaAssignmentState::Draft->value, KcaAssignmentState::Approved->value])
            ->get();

        $mentor = $this->currentMentor($enrollment);
        $snapshot = $activity->snapshot($enrollment);

        return ApiResponse::success($request, [
            'enrolled' => true,
            'is_mentor' => $isMentor,
            'enrollment' => $this->enrollmentPayload($enrollment),
            'modules_total' => $snapshot['curriculum']['modules_total'],
            'modules_with_progress' => $snapshot['curriculum']['modules_completed'] > 0
                ? $snapshot['curriculum']['modules_completed']
                : collect($snapshot['curriculum']['modules'])->filter(fn (array $row): bool => $row['lessons_completed'] > 0)->count(),
            'curriculum_percent' => $snapshot['curriculum']['percent'],
            'lessons_completed' => $snapshot['curriculum']['lessons_completed'],
            'lessons_total' => $snapshot['curriculum']['lessons_total'],
            'chapters_completed' => $snapshot['curriculum']['chapters_completed'],
            'chapters_total' => $snapshot['curriculum']['chapters_total'],
            'assignments_open' => $assignments->count(),
            'assignments_due_soon' => $assignments
                ->filter(fn (KcaAssignment $row) => $row->due_at !== null && $row->due_at->isBefore(now()->addDays(7)))
                ->count(),
            'attendance_recorded' => $snapshot['attendance_recorded'],
            'mentor' => $mentor,
            'certificate' => $enrollment->certificate
                ? [
                    'id' => $enrollment->certificate->public_id,
                    'certificate_number' => $enrollment->certificate->certificate_number ?? null,
                    'issued_at' => $enrollment->certificate->issued_at?->toIso8601String(),
                ]
                : null,
            'activity' => $snapshot,
        ]);
    }

    public function orientation(Request $request, BuildKcaOrientationProgramAction $program): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->activeEnrollment($person);
        $application = KcaApplication::query()
            ->where('person_id', $person->getKey())
            ->latest('id')
            ->first();

        if ($application === null && $enrollment === null) {
            throw new AccessDeniedHttpException('Orientation is not available yet.');
        }

        $applicationStatus = $application?->status instanceof KcaApplicationState
            ? $application->status
            : ($application !== null ? KcaApplicationState::from((string) $application->status) : null);
        $orientationProgress = collect($application?->orientation_progress ?? [])
            ->filter(fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();
        $orientationCompletedAt = $application?->orientation_completed_at?->utc()->toIso8601String();
        $canComplete = $orientationCompletedAt === null
            && $applicationStatus === KcaApplicationState::Interview
            && $application !== null;

        $programPayload = $program->handle(
            $person,
            $enrollment,
            $applicationStatus?->value,
            $orientationCompletedAt,
            $orientationProgress,
            $canComplete,
        );

        return ApiResponse::success($request, [
            'enrolled' => $enrollment !== null,
            'enrollment' => $enrollment === null ? null : $this->enrollmentPayload($enrollment),
            'application_status' => $applicationStatus?->value,
            'orientation_completed_at' => $orientationCompletedAt,
            'stages_completed' => $orientationProgress,
            'can_complete' => $canComplete,
            ...$programPayload,
        ]);
    }

    public function completeOrientationStage(Request $request, string $stage, RecordKcaOrientationStageAction $action): JsonResponse
    {
        $person = $this->person($request);
        $application = $action->handle($person, $stage);

        return ApiResponse::success($request, [
            'application_id' => $application->public_id,
            'status' => $application->status->value,
            'stages_completed' => $application->orientation_progress ?? [],
        ]);
    }

    public function completeOrientation(Request $request, CompleteKcaOrientationAction $action): JsonResponse
    {
        $person = $this->person($request);
        $user = $request->user();
        if ($user === null) {
            throw new AccessDeniedHttpException('Authentication required.');
        }
        $application = $action->handleForApplicant($person, $user);

        return ApiResponse::success($request, [
            'application_id' => $application->public_id,
            'status' => $application->status->value,
            'orientation_completed_at' => $application->orientation_completed_at?->utc()->toIso8601String(),
            'destination' => $application->status->destination(),
        ]);
    }

    public function practicalService(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->activeEnrollment($person);
        if ($enrollment === null) {
            return ApiResponse::success($request, [
                'enrolled' => false,
                'departments' => [],
                'departments_count' => 0,
                'hours_served' => 0,
                'attendance_recorded' => 0,
                'status' => 'not_enrolled',
                'on_track' => false,
            ]);
        }

        $departments = KcaAssignment::query()
            ->with([
                'module:id,public_id,code,title,sequence',
                'lesson:id,public_id,code,title,sequence,kca_module_id',
            ])
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->where('state', '!=', KcaAssignmentState::Draft->value)
            ->orderBy('assigned_at')
            ->get()
            ->map(function (KcaAssignment $assignment): array {
                $state = $assignment->state instanceof \BackedEnum
                    ? $assignment->state->value
                    : (string) $assignment->state;

                return [
                    'id' => $assignment->public_id,
                    'title' => $assignment->title,
                    'state' => $state,
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'module' => $assignment->module ? [
                        'id' => $assignment->module->public_id,
                        'title' => $assignment->module->title,
                        'code' => $assignment->module->code,
                    ] : null,
                    'lesson' => $assignment->lesson ? [
                        'id' => $assignment->lesson->public_id,
                        'title' => $assignment->lesson->title,
                        'code' => $assignment->lesson->code,
                    ] : null,
                ];
            })
            ->values()
            ->all();

        $attendance = KcaAttendance::query()
            ->with('lesson:id,estimated_minutes')
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->get();
        $minutes = $attendance->sum(fn (KcaAttendance $row): int => (int) ($row->lesson?->estimated_minutes ?? 0));

        $count = count($departments);

        return ApiResponse::success($request, [
            'enrolled' => true,
            'departments' => $departments,
            'departments_count' => $count,
            'hours_served' => (int) floor($minutes / 60),
            'attendance_recorded' => $attendance->count(),
            'status' => $count >= 2 ? 'on_track' : 'needs_departments',
            'on_track' => $count >= 2,
        ]);
    }

    public function modules(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->activeEnrollment($person);
        if ($enrollment === null) {
            throw new NotFoundHttpException('KCA learning is available after admission and activation.');
        }

        $completedLessonSet = $this->completedLessonSet($enrollment);
        $modules = KcaModule::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->with(['lessons' => fn ($q) => $q->orderBy('sequence')->with(['chapters' => fn ($c) => $c->orderBy('sequence')])])
            ->orderBy('sequence')
            ->get()
            ->map(fn (KcaModule $module) => $this->modulePayload($module, $enrollment, completedLessonSet: $completedLessonSet));

        return ApiResponse::success($request, $modules->values()->all());
    }

    public function module(Request $request, string $module, CompleteKcaLessonAction $complete): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $row = KcaModule::query()
            ->where('public_id', $module)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->with(['lessons' => fn ($q) => $q->orderBy('sequence')->with(['chapters' => fn ($c) => $c->orderBy('sequence')])])
            ->firstOrFail();

        return ApiResponse::success($request, $this->modulePayload(
            $row,
            $enrollment,
            detailed: true,
            complete: $complete,
            completedLessonSet: $this->completedLessonSet($enrollment),
        ));
    }

    public function lesson(Request $request, string $lesson, CompleteKcaLessonAction $complete, CompleteKcaChapterAction $completeChapter, KcaLessonUnlockToken $tokens): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $target = KcaLesson::query()->with(['module', 'chapters' => fn ($q) => $q->orderBy('sequence')])->where('public_id', $lesson)->firstOrFail();
        $complete->assertAccessible($enrollment, $target);
        $completedChapterIds = KcaChapterProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereIn('kca_chapter_id', $target->chapters->pluck('id'))
            ->whereNotNull('completed_at')
            ->pluck('kca_chapter_id')
            ->all();
        $completedSet = array_flip($completedChapterIds);

        return ApiResponse::success($request, [
            'id' => $target->public_id,
            'module_id' => $target->module?->public_id,
            'code' => $target->code,
            'title' => $target->title,
            'summary' => $target->summary,
            'body' => $target->body,
            'content_url' => $target->content_url,
            'estimated_minutes' => $target->estimated_minutes,
            'sequence' => $target->sequence,
            'day_index' => $target->day_index,
            'lesson_type' => $target->lesson_type ?? 'text',
            'requires_acknowledgement' => (bool) $target->requires_acknowledgement,
            'unlocked' => true,
            'unlock_token' => $tokens->issue($enrollment, $target),
            'chapters' => $target->chapters->map(function (KcaChapter $chapter) use ($enrollment, $completeChapter, $completedSet) {
                $unlocked = false;
                try {
                    $completeChapter->assertAccessible($enrollment, $chapter);
                    $unlocked = true;
                } catch (\Throwable) {
                    $unlocked = false;
                }

                return [
                    'id' => $chapter->public_id,
                    'code' => $chapter->code,
                    'title' => $chapter->title,
                    'summary' => $chapter->summary,
                    'sequence' => $chapter->sequence,
                    'estimated_minutes' => $chapter->estimated_minutes,
                    'completed' => isset($completedSet[$chapter->getKey()]),
                    'unlocked' => $unlocked,
                ];
            })->values()->all(),
        ]);
    }

    public function chapter(Request $request, string $chapter, CompleteKcaChapterAction $completeChapter, CompleteKcaLessonAction $complete, KcaLessonUnlockToken $tokens): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $target = KcaChapter::query()->with('lesson.module')->where('public_id', $chapter)->firstOrFail();
        $completeChapter->assertAccessible($enrollment, $target);
        $lesson = $target->lesson;

        return ApiResponse::success($request, [
            'id' => $target->public_id,
            'lesson_id' => $lesson?->public_id,
            'module_id' => $lesson?->module?->public_id,
            'code' => $target->code,
            'title' => $target->title,
            'summary' => $target->summary,
            'body' => $target->body,
            'content_url' => $target->content_url,
            'estimated_minutes' => $target->estimated_minutes,
            'sequence' => $target->sequence,
            'unlocked' => true,
            'unlock_token' => $lesson ? $tokens->issue($enrollment, $lesson) : null,
        ]);
    }

    public function completeChapter(Request $request, string $chapter, CompleteKcaChapterAction $action): JsonResponse
    {
        $data = $request->validate([
            'acknowledged' => ['sometimes', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'unlock_token' => ['nullable', 'string', 'size:64'],
        ]);
        $target = KcaChapter::query()->where('public_id', $chapter)->firstOrFail();
        try {
            $progress = $action->handle(
                $this->person($request),
                $target,
                (bool) ($data['acknowledged'] ?? false),
                $request->user(),
                $data['idempotency_key'] ?? null,
                $data['unlock_token'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (KcaIdempotencyConflictException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return ApiResponse::success($request, [
            'id' => $progress->public_id,
            'chapter_id' => $target->public_id,
            'completed_at' => $progress->completed_at?->utc()->toIso8601String(),
        ]);
    }

    public function completeLesson(Request $request, string $lesson, CompleteKcaLessonAction $action): JsonResponse
    {
        $data = $request->validate([
            'acknowledged' => ['sometimes', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'unlock_token' => ['nullable', 'string', 'size:64'],
        ]);
        $target = KcaLesson::query()->where('public_id', $lesson)->firstOrFail();
        try {
            $progress = $action->handle(
                $this->person($request),
                $target,
                (bool) ($data['acknowledged'] ?? false),
                $request->user(),
                $data['idempotency_key'] ?? null,
                $data['unlock_token'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (KcaIdempotencyConflictException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return ApiResponse::success($request, [
            'id' => $progress->public_id,
            'lesson_id' => $target->public_id,
            'completed_at' => $progress->completed_at?->utc()->toIso8601String(),
        ]);
    }

    public function assignments(Request $request, KcaStudentActivityQuery $activity): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);

        $rows = KcaAssignment::query()
            ->with([
                'module:id,public_id,code,title,sequence',
                'lesson:id,public_id,code,title,sequence,kca_module_id',
            ])
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->where('state', '!=', KcaAssignmentState::Draft->value)
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->get()
            ->map(fn (KcaAssignment $assignment) => $activity->assignmentPayload($assignment));

        return ApiResponse::success($request, $rows->values()->all());
    }

    public function assignment(Request $request, string $assignment, KcaStudentActivityQuery $activity): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $row = KcaAssignment::query()
            ->with([
                'module:id,public_id,code,title,sequence',
                'lesson:id,public_id,code,title,sequence,kca_module_id',
            ])
            ->where('public_id', $assignment)
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->where('state', '!=', KcaAssignmentState::Draft->value)
            ->firstOrFail();

        return ApiResponse::success($request, $activity->assignmentPayload($row));
    }

    public function submitEvidence(Request $request, string $assignment, SubmitKcaEvidenceAction $action): JsonResponse
    {
        $request->merge([
            'idempotency_key' => $request->header('Idempotency-Key') ?? $request->input('idempotency_key'),
        ]);
        $data = $request->validate([
            'file_asset_id' => ['required', 'ulid', 'exists:file_assets,public_id'],
            'idempotency_key' => ['required', 'string', 'between:1,255'],
        ]);
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $target = KcaAssignment::query()
            ->where('public_id', $assignment)
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->firstOrFail();
        $fileAsset = FileAsset::query()->where('public_id', $data['file_asset_id'])->firstOrFail();
        $user = $request->user();
        if ($user === null) {
            throw new UnprocessableEntityHttpException('Authentication is required.');
        }
        try {
            $evidence = $action->handle(
                $target,
                $enrollment,
                $fileAsset,
                $person,
                (string) $data['idempotency_key'],
                $user,
            );
        } catch (InvalidArgumentException|KcaEvidenceOwnershipException|KcaEvidenceUnavailableException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        } catch (KcaIdempotencyConflictException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return ApiResponse::success($request, [
            'id' => $evidence->public_id,
            'assignment_id' => $target->public_id,
            'file_asset_id' => $fileAsset->public_id,
            'submitted_at' => $evidence->submitted_at?->utc()->toIso8601String(),
        ], status: 201);
    }

    public function downloadCertificate(Request $request, KcaCertificatePdfRenderer $pdf): Response
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $enrollment->load(['certificate.revocation', 'person']);
        $certificate = $enrollment->certificate;
        if ($certificate === null || $certificate->revocation !== null) {
            throw new NotFoundHttpException('No KCA certificate is available for download.');
        }
        $holder = PersonDisplayName::of($enrollment->person) ?: 'Member';
        $bytes = $pdf->render(
            $holder,
            (string) ($certificate->certificate_number ?? $certificate->public_id),
            $certificate->issued_at?->toDateString() ?? now()->toDateString(),
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="kca-certificate.pdf"',
        ]);
    }

    public function mentor(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $mentor = $this->currentMentor($enrollment);

        if ($mentor === null) {
            return ApiResponse::success($request, [
                'assigned' => false,
                'mentor' => null,
                'message' => 'No mentor is currently assigned to your enrollment.',
            ]);
        }

        return ApiResponse::success($request, [
            'assigned' => true,
            'mentor' => $mentor,
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);

        $rows = KcaAttendance::query()
            ->with('lesson:id,public_id,code,title,sequence,kca_module_id')
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->orderByDesc('session_on')
            ->limit(100)
            ->get()
            ->map(fn (KcaAttendance $row) => [
                'id' => $row->public_id,
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : $row->status,
                'session_on' => $row->session_on?->toDateString(),
                'recorded_at' => $row->recorded_at?->toIso8601String(),
                'lesson' => $row->lesson ? [
                    'id' => $row->lesson->public_id,
                    'code' => $row->lesson->code,
                    'title' => $row->lesson->title,
                    'sequence' => $row->lesson->sequence,
                ] : null,
            ]);

        return ApiResponse::success($request, $rows->values()->all());
    }

    public function storeNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:20000'],
            'lesson_id' => ['nullable', 'ulid', 'exists:kca_lessons,public_id'],
            'chapter_id' => ['nullable', 'ulid', 'exists:kca_chapters,public_id'],
        ]);
        $enrollment = $this->requireEnrollment($this->person($request));
        $lesson = isset($data['lesson_id']) ? KcaLesson::query()->where('public_id', $data['lesson_id'])->first() : null;
        $chapter = isset($data['chapter_id']) ? KcaChapter::query()->where('public_id', $data['chapter_id'])->first() : null;
        $note = KcaStudyNote::query()->create([
            'kca_enrollment_id' => $enrollment->getKey(),
            'kca_lesson_id' => $lesson?->getKey(),
            'kca_chapter_id' => $chapter?->getKey(),
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
        ]);

        return ApiResponse::success($request, [
            'id' => $note->public_id,
            'title' => $note->title,
            'body' => $note->body,
            'updated_at' => $note->updated_at?->utc()->toIso8601String(),
        ], status: 201);
    }

    public function notes(Request $request): JsonResponse
    {
        $enrollment = $this->requireEnrollment($this->person($request));
        $rows = KcaStudyNote::query()
            ->with(['lesson:id,public_id,title', 'chapter:id,public_id,title'])
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (KcaStudyNote $note): array => [
                'id' => $note->public_id,
                'title' => $note->title,
                'body' => $note->body,
                'lesson_id' => $note->lesson?->public_id,
                'chapter_id' => $note->chapter?->public_id,
                'updated_at' => $note->updated_at?->utc()->toIso8601String(),
            ]);

        return ApiResponse::success($request, $rows->values()->all());
    }

    public function storeDevotional(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'source' => ['nullable', 'string', 'max:191'],
            'publication_id' => ['nullable', 'ulid'],
            'reflection' => ['nullable', 'string', 'max:20000'],
            'read_at' => ['nullable', 'date'],
        ]);
        $enrollment = $this->requireEnrollment($this->person($request));
        $row = KcaDevotionalReading::query()->create([
            'kca_enrollment_id' => $enrollment->getKey(),
            'title' => $data['title'],
            'source' => $data['source'] ?? null,
            'publication_id' => $data['publication_id'] ?? null,
            'reflection' => $data['reflection'] ?? null,
            'read_at' => isset($data['read_at']) ? CarbonImmutable::parse($data['read_at']) : now()->utc(),
        ]);

        return ApiResponse::success($request, [
            'id' => $row->public_id,
            'title' => $row->title,
            'source' => $row->source,
            'publication_id' => $row->publication_id,
            'reflection' => $row->reflection,
            'read_at' => $row->read_at?->utc()->toIso8601String(),
        ], status: 201);
    }

    public function storeSoulWin(Request $request, string $assignment, RecordKcaSoulWinAction $action): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'ulid'],
            'given_name' => ['required', 'string', 'max:191'],
            'family_name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $enrollment = $this->requireEnrollment($this->person($request));
        $target = KcaAssignment::query()
            ->where('public_id', $assignment)
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->firstOrFail();
        $parent = isset($data['parent_id'])
            ? KcaSoulWin::query()->where('public_id', $data['parent_id'])->where('kca_assignment_id', $target->getKey())->firstOrFail()
            : null;
        $user = $request->user();
        if ($user === null) {
            throw new UnprocessableEntityHttpException('Authentication is required.');
        }
        try {
            $soul = $action->handle($target, $parent, $data, $user);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return ApiResponse::success($request, [
            'id' => $soul->public_id,
            'depth' => $soul->depth,
            'given_name' => $soul->given_name,
            'family_name' => $soul->family_name,
            'soul_tree' => app(KcaSoulTreeService::class)->progress($target->fresh()),
        ], status: 201);
    }

    public function mentees(Request $request, KcaStudentActivityQuery $activity): JsonResponse
    {
        $person = $this->person($request);
        $rows = $this->menteeAssignmentsFor($person)
            ->map(function (KcaMentorAssignment $row) use ($activity): array {
                $enrollment = $row->enrollment;
                $snapshot = $enrollment ? $activity->snapshot($enrollment) : null;

                return [
                    'assignment_id' => $row->public_id,
                    'enrollment_id' => $enrollment?->public_id,
                    'person' => $enrollment?->person ? [
                        'id' => $enrollment->person->public_id,
                        'name' => PersonDisplayName::of($enrollment->person),
                    ] : null,
                    'curriculum_percent' => $snapshot['curriculum']['percent'] ?? 0,
                    'assignments_open' => $snapshot['assignments']['open'] ?? 0,
                    'bible_percent' => $snapshot['bible']['enrollment']['percent'] ?? null,
                    'notes_count' => $snapshot['notes']['count'] ?? 0,
                    'devotionals_count' => $snapshot['devotionals']['count'] ?? 0,
                ];
            })
            ->values()
            ->all();

        return ApiResponse::success($request, $rows);
    }

    public function mentee(Request $request, string $enrollment, KcaStudentActivityQuery $activity): JsonResponse
    {
        $person = $this->person($request);
        $target = $this->menteeAssignmentsFor($person)
            ->first(fn (KcaMentorAssignment $row): bool => $row->enrollment?->public_id === $enrollment);
        if ($target?->enrollment === null) {
            throw new NotFoundHttpException('That mentee is not assigned to you.');
        }

        return ApiResponse::success($request, $activity->snapshot($target->enrollment));
    }

    private function activeEnrollment(Person $person): ?KcaEnrollment
    {
        return KcaEnrollment::query()
            ->with(['year:id,public_id,name', 'cohort:id,public_id,name', 'certificate'])
            ->where('person_id', $person->getKey())
            ->latest('starts_on')
            ->first();
    }

    private function requireEnrollment(Person $person): KcaEnrollment
    {
        $enrollment = $this->activeEnrollment($person);
        if ($enrollment === null) {
            throw new NotFoundHttpException('No KCA enrollment was found for this account.');
        }

        return $enrollment;
    }

    /** @return array<string, mixed> */
    private function enrollmentPayload(KcaEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->public_id,
            'starts_on' => $enrollment->starts_on?->toDateString(),
            'year' => $enrollment->year ? [
                'id' => $enrollment->year->public_id,
                'name' => $enrollment->year->name ?? null,
            ] : null,
            'cohort' => $enrollment->cohort ? [
                'id' => $enrollment->cohort->public_id,
                'name' => $enrollment->cohort->name ?? null,
            ] : null,
        ];
    }

    /** @return array<int, true> */
    private function completedLessonSet(KcaEnrollment $enrollment): array
    {
        $ids = KcaLessonProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereNotNull('completed_at')
            ->pluck('kca_lesson_id')
            ->all();

        return array_flip($ids);
    }

    /** @return array<string, mixed> */
    private function modulePayload(
        KcaModule $module,
        ?KcaEnrollment $enrollment = null,
        bool $detailed = false,
        ?CompleteKcaLessonAction $complete = null,
        ?array $completedLessonSet = null,
    ): array {
        $payload = [
            'id' => $module->public_id,
            'code' => $module->code,
            'title' => $module->title,
            'sequence' => $module->sequence,
            'duration_days' => $module->duration_days,
            'is_active' => (bool) $module->is_active,
            'published_at' => $module->published_at?->utc()->toIso8601String(),
            'lessons_count' => $module->relationLoaded('lessons') ? $module->lessons->count() : null,
        ];

        if ($enrollment !== null && $module->relationLoaded('lessons')) {
            $lessonTotal = $module->lessons->count();
            $lessonDone = $completedLessonSet === null
                ? 0
                : $module->lessons->filter(fn (KcaLesson $lesson): bool => isset($completedLessonSet[$lesson->getKey()]))->count();
            $payload['lessons_total'] = $lessonTotal;
            $payload['lessons_completed'] = $lessonDone;
            $payload['percent'] = $lessonTotal === 0 ? 0 : (int) round(($lessonDone / $lessonTotal) * 100);
            $payload['progress_state'] = match (true) {
                $lessonTotal > 0 && $lessonDone >= $lessonTotal => 'completed',
                $lessonDone > 0 => 'in_progress',
                default => 'not_started',
            };
        }

        if ($detailed) {
            $payload['lessons'] = $module->lessons->map(function ($lesson) use ($enrollment, $complete, $completedLessonSet) {
                $unlocked = false;
                if ($enrollment !== null && $complete !== null) {
                    try {
                        $complete->assertAccessible($enrollment, $lesson);
                        $unlocked = true;
                    } catch (\Throwable) {
                        $unlocked = false;
                    }
                }
                $row = [
                    'id' => $lesson->public_id,
                    'code' => $lesson->code,
                    'title' => $lesson->title,
                    'sequence' => $lesson->sequence,
                    'day_index' => $lesson->day_index,
                    'lesson_type' => $lesson->lesson_type ?? 'text',
                    'estimated_minutes' => $lesson->estimated_minutes,
                    'unlocked' => $unlocked,
                    'completed' => $completedLessonSet !== null && isset($completedLessonSet[$lesson->getKey()]),
                    'chapters_count' => $lesson->relationLoaded('chapters') ? $lesson->chapters->count() : null,
                    'chapters' => $lesson->relationLoaded('chapters')
                        ? $lesson->chapters->map(fn (KcaChapter $chapter): array => [
                            'id' => $chapter->public_id,
                            'code' => $chapter->code,
                            'title' => $chapter->title,
                            'sequence' => $chapter->sequence,
                        ])->values()->all()
                        : [],
                ];
                if (! $unlocked) {
                    $row['lock_reason'] = 'This daily bundle opens on the next eligible learning day after the previous bundle is complete.';
                }

                return $row;
            })->values()->all();
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function currentMentor(KcaEnrollment $enrollment): ?array
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
    private function mentorDisplayName(?array $mentor): ?string
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

    /**
     * @return Collection<int, KcaMentorAssignment>
     */
    private function menteeAssignmentsFor(Person $person)
    {
        return KcaMentorAssignment::query()
            ->with(['enrollment.person.profile', 'enrollment.year', 'enrollment.cohort', 'enrollment.certificate'])
            ->where('mentor_person_id', $person->getKey())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->get();
    }
}
