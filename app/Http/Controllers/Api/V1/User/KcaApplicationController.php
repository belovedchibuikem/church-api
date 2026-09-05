<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\SubmitKcaApplicationRequest;
use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Kca\RequestKcaLeadershipRecommendationAction;
use App\Support\Kca\ResolveKcaAccessQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class KcaApplicationController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function me(Request $request, ResolveKcaAccessQuery $query): JsonResponse
    {
        return ApiResponse::success($request, $query->handle($this->person($request)));
    }

    public function showCurrent(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $application = KcaApplication::query()
            ->where('person_id', $person->getKey())
            ->latest('id')
            ->first();

        if ($application === null) {
            return ApiResponse::success($request, null);
        }

        $status = $application->status instanceof KcaApplicationState
            ? $application->status
            : KcaApplicationState::from((string) $application->status);

        return ApiResponse::success($request, [
            'id' => $application->public_id,
            'status' => $status->value,
            'label' => $status->publicLabel(),
            'destination' => $status->destination(),
            'application_data' => $status->allowsApplicantEdit() ? $application->application_data : null,
            'received_at' => $application->received_at?->utc()->toIso8601String(),
        ]);
    }

    public function store(SubmitKcaApplicationRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $finalize = $request->boolean('finalize', true);
        $incoming = $this->applicantSafeData($request->validated('application_data'));

        $application = KcaApplication::query()
            ->where('person_id', $person->getKey())
            ->whereNotIn('status', [
                KcaApplicationState::NotAccepted->value,
                KcaApplicationState::Withdrawn->value,
                KcaApplicationState::Revoked->value,
            ])
            ->latest('id')
            ->first();

        $wasRecentlyCreated = false;
        if ($application === null) {
            $application = new KcaApplication([
                'person_id' => $person->getKey(),
                'received_at' => now()->utc(),
            ]);
            $application->status = KcaApplicationState::Draft;
            $wasRecentlyCreated = true;
        } else {
            $status = $application->status instanceof KcaApplicationState
                ? $application->status
                : KcaApplicationState::from((string) $application->status);
            if ($finalize && $status === KcaApplicationState::Received && $application->application_data == $incoming) {
                return ApiResponse::success($request, [
                    'id' => $application->public_id,
                    'status' => $status->value,
                    'application_data' => $application->application_data,
                    'received_at' => $application->received_at?->utc()->toIso8601String(),
                ]);
            }
            if (! $status->allowsApplicantEdit() && $status !== KcaApplicationState::Received) {
                throw new ConflictHttpException('This application can no longer be edited by the applicant.');
            }
            if ($status === KcaApplicationState::Received && $finalize) {
                throw new ConflictHttpException('Submitted answers cannot be changed. Wait for a request-information decision.');
            }
        }

        $status = $finalize ? KcaApplicationState::Received : KcaApplicationState::Draft;
        if ($application->status === KcaApplicationState::InformationRequired && $finalize) {
            $status = KcaApplicationState::Received;
        }

        $application->application_data = $incoming;
        $application->status = $status;
        if ($finalize && $application->received_at === null) {
            $application->received_at = now()->utc();
        }
        $application->save();

        $phone = trim((string) ($incoming['phone'] ?? $incoming['mobile'] ?? ''));
        if ($phone !== '') {
            $person->loadMissing('profile');
            $person->profile?->forceFill(['phone' => $phone])->save();
        }

        $this->syncLeadershipRecommendation($application, $incoming, $request->user());

        return ApiResponse::success($request, [
            'id' => $application->public_id,
            'status' => $application->status->value,
            'application_data' => $application->application_data,
            'received_at' => $application->received_at?->utc()->toIso8601String(),
        ], status: $wasRecentlyCreated ? 201 : 200);
    }

    /** @param  array<string, mixed>  $incoming */
    private function applicantSafeData(array $incoming): array
    {
        foreach ([
            'recommendation',
            'recommendation_comments',
            'recommendation_approved',
            'recommendation_status',
            'approved',
            'leadership_approved',
            'statement',
            'verifier_token',
        ] as $denied) {
            unset($incoming[$denied]);
        }

        return $incoming;
    }

    /** @param  array<string, mixed>  $incoming */
    private function syncLeadershipRecommendation(KcaApplication $application, array $incoming, mixed $actor): void
    {
        $email = isset($incoming['recommender_email']) ? trim((string) $incoming['recommender_email']) : '';
        $name = isset($incoming['recommender_name']) ? trim((string) $incoming['recommender_name']) : '';
        if ($email === '' || $name === '') {
            return;
        }
        try {
            app(RequestKcaLeadershipRecommendationAction::class)->handle(
                $application,
                $name,
                $email,
                isset($incoming['recommender_position']) ? (string) $incoming['recommender_position'] : (isset($incoming['recommender_role']) ? (string) $incoming['recommender_role'] : null),
                isset($incoming['recommender_phone']) ? (string) $incoming['recommender_phone'] : null,
                $actor instanceof User ? $actor : null,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }
}
