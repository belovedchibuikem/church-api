<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\CurrentUserResource;
use App\Http\Resources\Api\V1\User\UserPaymentIntentResource;
use App\Models\CommunicationNotification;
use App\Models\PaymentIntent;
use App\Models\PrayerRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $person = $this->person($request);
        $user->loadMissing(['person.profile', 'person.preference']);

        $unreadNotificationCount = CommunicationNotification::query()
            ->whereNull('read_at')
            ->where(function ($query) use ($user, $person): void {
                $query->where('user_id', $user->getKey())
                    ->orWhere('person_id', $person->getKey());
            })
            ->count();

        $recentIntents = PaymentIntent::query()
            ->where('payer_person_id', $person->getKey())
            ->latest('created_at')
            ->limit(5)
            ->get();

        $openPrayerCount = PrayerRequest::query()
            ->where('person_id', $person->getKey())
            ->where('status', 'open')
            ->count();

        return ApiResponse::success($request, [
            'profile' => CurrentUserResource::make($user)->resolve($request),
            'unread_notification_count' => $unreadNotificationCount,
            'recent_payment_intents' => UserPaymentIntentResource::collection($recentIntents)->resolve($request),
            'open_prayer_count' => $openPrayerCount,
            'upcoming_note' => null,
        ]);
    }
}
