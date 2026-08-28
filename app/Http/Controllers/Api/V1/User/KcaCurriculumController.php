<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Kca\KcaAssignmentState;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaEnrollment;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\Person;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only member KCA curriculum surface.
 *
 * Evidence submit / grading / certificate issuance remain OD-008 gated and are
 * not exposed here — clients should keep those actions unavailable.
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
                'modules_total' => KcaModule::query()->where('is_active', true)->count(),
                'modules_with_progress' => 0,
                'assignments_open' => 0,
                'assignments_due_soon' => 0,
                'attendance_recorded' => 0,
                'mentor' => null,
                'certificate' => null,
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
        $modules = KcaModule::query()
            ->where('is_active', true)
            ->with(['lessons' => fn ($q) => $q->orderBy('sequence')])
            ->orderBy('sequence')
            ->get()
            ->map(fn (KcaModule $module) => $this->modulePayload($module));

        return ApiResponse::success($request, $modules->values()->all());
    }

    public function module(Request $request, string $module): JsonResponse
    {
        $row = KcaModule::query()
            ->where('public_id', $module)
            ->where('is_active', true)
            ->with(['lessons' => fn ($q) => $q->orderBy('sequence')])
            ->firstOrFail();

        return ApiResponse::success($request, $this->modulePayload($row, detailed: true));
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
    private function modulePayload(KcaModule $module, bool $detailed = false): array
    {
        $payload = [
            'id' => $module->public_id,
            'code' => $module->code,
            'title' => $module->title,
            'sequence' => $module->sequence,
            'is_active' => (bool) $module->is_active,
            'lessons_count' => $module->relationLoaded('lessons') ? $module->lessons->count() : null,
        ];

        if ($detailed) {
            $payload['lessons'] = $module->lessons->map(fn ($lesson) => [
                'id' => $lesson->public_id,
                'code' => $lesson->code,
                'title' => $lesson->title,
                'sequence' => $lesson->sequence,
            ])->values()->all();
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
