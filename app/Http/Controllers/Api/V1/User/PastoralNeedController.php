<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StorePastoralNeedRequest;
use App\Http\Resources\Api\V1\User\PastoralNeedResource;
use App\Models\PastoralNeed;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PastoralNeedController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function index(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $needs = PastoralNeed::query()
            ->where('person_id', $person->getKey())
            ->latest('created_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            $request,
            PastoralNeedResource::collection($needs)->resolve($request),
        );
    }

    public function store(StorePastoralNeedRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $validated = $request->validated();

        $need = new PastoralNeed;
        $need->forceFill([
            'person_id' => $person->getKey(),
            'category' => $validated['category'],
            'summary' => $validated['summary'],
            'status' => 'open',
        ])->save();

        return ApiResponse::success(
            $request,
            PastoralNeedResource::make($need)->resolve($request),
            status: 201,
        );
    }
}
