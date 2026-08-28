<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\GrantConsentRequest;
use App\Http\Resources\Api\V1\User\ConsentResource;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Identity\GrantPersonConsentAction;
use App\Support\Identity\WithdrawPersonConsentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $consents = $person->consents()->latest('granted_at')->get();

        return ApiResponse::success(
            $request,
            ConsentResource::collection($consents)->resolve($request),
        );
    }

    public function store(
        GrantConsentRequest $request,
        GrantPersonConsentAction $grantConsent,
    ): JsonResponse {
        $person = $this->person($request);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $validated = $request->validated();

        $consent = $grantConsent->handle(
            person: $person,
            purpose: $validated['purpose'],
            policyVersion: $validated['policy_version'],
            source: 'user_api',
            actor: $user,
        );

        return ApiResponse::success(
            $request,
            ConsentResource::make($consent)->resolve($request),
            status: 201,
        );
    }

    public function destroy(
        Request $request,
        string $consent,
        WithdrawPersonConsentAction $withdrawConsent,
    ): JsonResponse {
        $person = $this->person($request);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $ownedConsent = $person->consents()
            ->where('public_id', $consent)
            ->firstOrFail();

        $withdrawnConsent = $withdrawConsent->handle($ownedConsent, 'user_api', $user);

        return ApiResponse::success(
            $request,
            ConsentResource::make($withdrawnConsent)->resolve($request),
        );
    }

    private function person(Request $request): Person
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $person = $user->person;
        abort_unless($person instanceof Person, 409, 'The account is not linked to a person profile.');

        return $person;
    }
}
