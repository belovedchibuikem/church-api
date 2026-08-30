<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\SubmitKcaApplicationRequest;
use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KcaApplicationController extends Controller
{
    use ResolvesAuthenticatedPerson;

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

        return ApiResponse::success($request, [
            'id' => $application->public_id,
            'status' => $application->status->value,
            'application_data' => $application->application_data,
            'received_at' => $application->received_at?->utc()->toIso8601String(),
        ]);
    }

    public function store(SubmitKcaApplicationRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $finalize = $request->boolean('finalize', true);
        $status = $finalize ? KcaApplicationState::Received : KcaApplicationState::Draft;

        $application = KcaApplication::query()
            ->where('person_id', $person->getKey())
            ->whereIn('status', [
                KcaApplicationState::Draft->value,
                KcaApplicationState::Received->value,
                KcaApplicationState::Reviewed->value,
            ])
            ->latest('id')
            ->first();

        $wasRecentlyCreated = false;
        if ($application === null) {
            $application = new KcaApplication([
                'person_id' => $person->getKey(),
                'received_at' => now()->utc(),
            ]);
            $wasRecentlyCreated = true;
        }

        $application->application_data = $request->validated('application_data');
        $application->status = $status;

        if ($finalize && $application->received_at === null) {
            $application->received_at = now()->utc();
        }

        $application->save();

        return ApiResponse::success($request, [
            'id' => $application->public_id,
            'status' => $application->status->value,
            'application_data' => $application->application_data,
            'received_at' => $application->received_at?->utc()->toIso8601String(),
        ], status: $wasRecentlyCreated ? 201 : 200);
    }
}
