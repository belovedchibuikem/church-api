<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StorePrayerRequestRequest;
use App\Http\Resources\Api\V1\User\PrayerRequestResource;
use App\Models\PrayerRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrayerRequestController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function index(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $prayers = PrayerRequest::query()
            ->where('person_id', $person->getKey())
            ->latest('created_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            $request,
            PrayerRequestResource::collection($prayers)->resolve($request),
        );
    }

    public function store(StorePrayerRequestRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $validated = $request->validated();

        $prayer = new PrayerRequest;
        $prayer->forceFill([
            'person_id' => $person->getKey(),
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'status' => 'open',
        ])->save();

        return ApiResponse::success(
            $request,
            PrayerRequestResource::make($prayer)->resolve($request),
            status: 201,
        );
    }
}
