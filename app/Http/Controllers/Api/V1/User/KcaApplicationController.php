<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\SubmitKcaApplicationRequest;
use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class KcaApplicationController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function store(SubmitKcaApplicationRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $application = KcaApplication::query()
            ->where('person_id', $person->getKey())
            ->whereIn('status', [KcaApplicationState::Received->value, KcaApplicationState::Reviewed->value])
            ->latest('received_at')
            ->first();

        if ($application === null) {
            $application = new KcaApplication([
                'person_id' => $person->getKey(),
                'received_at' => now()->utc(),
            ]);
        }

        $application->application_data = $request->validated('application_data');
        $application->save();

        return ApiResponse::success($request, [
            'id' => $application->public_id,
            'status' => $application->status->value,
            'received_at' => $application->received_at?->utc()->toIso8601String(),
        ], status: $application->wasRecentlyCreated ? 201 : 200);
    }
}
