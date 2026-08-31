<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Exceptions\KcaEvidenceOwnershipException;
use App\Exceptions\KcaEvidenceUnavailableException;
use App\Exceptions\KcaIdempotencyConflictException;
use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Kca\KcaAssignmentState;
use App\Models\FileAsset;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\Person;
use App\Support\Api\ApiResponse;
use App\Support\Identity\PersonDisplayName;
use App\Support\Kca\CompleteKcaLessonAction;
use App\Support\Kca\KcaCertificatePdfRenderer;
use App\Support\Kca\KcaLessonUnlockToken;
use App\Support\Kca\SubmitKcaEvidenceAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Member KCA curriculum: published lessons, evidence submit, certificate PDF.
 */
class KcaCurriculumController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function dashboard(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->activeEnrollment($person);

        if ($enrollment === null) {
            return ApiResponse::success($request, [
                'enrolled' => false,
                'enrollment' => null,
                'modules_total' => 0,
                'modules_with_progress' => 0,
                'assignments_open' => 0,
                'assignments_due_soon' => 0,
                'attendance_recorded' => 0,
                'mentor' => null,
                'certificate' => null,
                'today_plan' => null,
            ]);
        }

        $assignments = KcaAssignment::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereNotIn('state', [KcaAssignmentState::Draft->value, KcaAssignmentState::Approved->value])
            ->get();

        $mentor = $this->currentMentor($enrollment);

        return ApiResponse::success($request, [
            'enrolled' => true,
            'enrollment' => $this->enrollmentPayload($enrollment),
            'modules_total' => KcaModule::query()->where('is_active', true)->count(),
            'modules_with_progress' => KcaAssignment::query()
                ->where('kca_enrollment_id', $enrollment->getKey())
                ->distinct('kca_module_id')
                ->count('kca_module_id'),
            'assignments_open' => $assignments->count(),
            'assignments_due_soon' => $assignments
                ->filter(fn (KcaAssignment $row) => $row->due_at !== null && $row->due_at->isBefore(now()->addDays(7)))
                ->count(),
            'attendance_recorded' => KcaAttendance::query()
                ->where('kca_enrollment_id', $enrollment->getKey())
                ->count(),
            'mentor' => $mentor,
            'certificate' => $enrollment->certificate
                ? [
                    'id' => $enrollment->certificate->public_id,
                    'certificate_number' => $enrollment->certificate->certificate_number ?? null,
                    'issued_at' => $enrollment->certificate->issued_at?->toIso8601String(),
                ]
                : null,
        ]);
    }

    public function modules(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->activeEnrollment($person);
        if ($enrollment === null) {
            throw new NotFoundHttpException('KCA learning is available after admission and activation.');
        }

        $modules = KcaModule::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->with(['lessons' => fn ($q) => $q->orderBy('sequence')])
            ->orderBy('sequence')
            ->get()
            ->map(fn (KcaModule $module) => $this->modulePayload($module, $enrollment));

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
            ->with(['lessons' => fn ($q) => $q->orderBy('sequence')])
            ->firstOrFail();

        return ApiResponse::success($request, $this->modulePayload($row, $enrollment, detailed: true, complete: $complete));
    }

    public function lesson(Request $request, string $lesson, CompleteKcaLessonAction $complete, KcaLessonUnlockToken $tokens): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $target = KcaLesson::query()->with('module')->where('public_id', $lesson)->firstOrFail();
        $complete->assertAccessible($enrollment, $target);

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

    public function assignments(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);

        $rows = KcaAssignment::query()
            ->with('module:id,public_id,code,title,sequence')
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->where('state', '!=', KcaAssignmentState::Draft->value)
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->get()
            ->map(fn (KcaAssignment $assignment) => [
                'id' => $assignment->public_id,
                'title' => $assignment->title,
                'state' => $assignment->state instanceof \BackedEnum ? $assignment->state->value : $assignment->state,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                'submitted_at' => $assignment->submitted_at?->toIso8601String(),
                'module' => $assignment->module ? [
                    'id' => $assignment->module->public_id,
                    'code' => $assignment->module->code,
                    'title' => $assignment->module->title,
                    'sequence' => $assignment->module->sequence,
                ] : null,
            ]);

        return ApiResponse::success($request, $rows->values()->all());
    }

    public function assignment(Request $request, string $assignment): JsonResponse
    {
        $person = $this->person($request);
        $enrollment = $this->requireEnrollment($person);
        $row = KcaAssignment::query()
            ->with('module:id,public_id,code,title,sequence')
            ->where('public_id', $assignment)
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->where('state', '!=', KcaAssignmentState::Draft->value)
            ->firstOrFail();

        return ApiResponse::success($request, [
            'id' => $row->public_id,
            'title' => $row->title,
            'state' => $row->state instanceof \BackedEnum ? $row->state->value : $row->state,
            'due_at' => $row->due_at?->toIso8601String(),
            'assigned_at' => $row->assigned_at?->toIso8601String(),
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'module' => $row->module ? [
                'id' => $row->module->public_id,
                'code' => $row->module->code,
                'title' => $row->module->title,
                'sequence' => $row->module->sequence,
            ] : null,
        ]);
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

    /** @return array<string, mixed> */
    private function modulePayload(KcaModule $module, ?KcaEnrollment $enrollment = null, bool $detailed = false, ?CompleteKcaLessonAction $complete = null): array
    {
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

        if ($detailed) {
            $payload['lessons'] = $module->lessons->map(function ($lesson) use ($enrollment, $complete) {
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
                    'unlocked' => $unlocked,
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
}
