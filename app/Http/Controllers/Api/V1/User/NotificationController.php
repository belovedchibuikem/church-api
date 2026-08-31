<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\NotificationResource;
use App\Models\CommunicationNotification;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function index(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $person = $this->person($request);

        $notifications = CommunicationNotification::query()
            ->with(['recipient.broadcast.template'])
            ->where(function ($query) use ($user, $person): void {
                $query->where('user_id', $user->getKey())
                    ->orWhere('person_id', $person->getKey());
            })
            ->latest('created_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            $request,
            NotificationResource::collection($notifications)->resolve($request),
        );
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $user = $this->actor($request);
        $person = $this->person($request);

        $owned = CommunicationNotification::query()
            ->with(['recipient.broadcast.template'])
            ->where('public_id', $notification)
            ->where(function ($query) use ($user, $person): void {
                $query->where('user_id', $user->getKey())
                    ->orWhere('person_id', $person->getKey());
            })
            ->firstOrFail();

        if ($owned->read_at === null) {
            $owned->forceFill(['read_at' => now()])->save();
        }

        return ApiResponse::success(
            $request,
            NotificationResource::make($owned->fresh(['recipient.broadcast.template']))->resolve($request),
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $person = $this->person($request);

        CommunicationNotification::query()
            ->whereNull('read_at')
            ->where(function ($query) use ($user, $person): void {
                $query->where('user_id', $user->getKey())
                    ->orWhere('person_id', $person->getKey());
            })
            ->update(['read_at' => now()]);

        return $this->index($request);
    }
}
