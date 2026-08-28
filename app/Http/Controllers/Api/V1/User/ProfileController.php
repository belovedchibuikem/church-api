<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\UpdateProfileRequest;
use App\Http\Resources\Api\V1\User\CurrentUserResource;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Identity\UpdatePersonProfileAction;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(
        UpdateProfileRequest $request,
        UpdatePersonProfileAction $updateProfile,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $person = $user->person;
        abort_unless($person instanceof Person, 409, 'The account is not linked to a person profile.');

        $updateProfile->handle($person, $request->validated(), $user);
        $user->loadMissing(['person.profile', 'person.preference']);

        return ApiResponse::success(
            $request,
            CurrentUserResource::make($user)->resolve($request),
        );
    }
}
