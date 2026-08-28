<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\UpdatePreferencesRequest;
use App\Http\Resources\Api\V1\User\PreferenceResource;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Identity\UpdatePersonPreferencesAction;
use Illuminate\Http\JsonResponse;

class PreferenceController extends Controller
{
    public function update(
        UpdatePreferencesRequest $request,
        UpdatePersonPreferencesAction $updatePreferences,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $person = $user->person;
        abort_unless($person instanceof Person, 409, 'The account is not linked to a person profile.');

        $validated = $request->validated();
        $preference = $updatePreferences->handle(
            person: $person,
            locale: $validated['locale'],
            timezone: $validated['timezone'],
            notificationChannels: $validated['notification_channels'],
            actor: $user,
        );

        return ApiResponse::success(
            $request,
            PreferenceResource::make($preference)->resolve($request),
        );
    }
}
