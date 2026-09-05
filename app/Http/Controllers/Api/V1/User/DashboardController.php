<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Church\ChurchMembershipStatus;
use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\CurrentUserResource;
use App\Http\Resources\Api\V1\User\UserPaymentIntentResource;
use App\Models\BibleReadingPosition;
use App\Models\CommunicationNotification;
use App\Models\MinistryEvent;
use App\Models\PaymentIntent;
use App\Models\PrayerRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

        $churchIds = $person->memberships()
            ->where('status', ChurchMembershipStatus::Active->value)
            ->pluck('church_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $readerBaseQuery = BibleReadingPosition::query();
        if ($churchIds !== []) {
            $readerBaseQuery->whereHas('person.memberships', function ($query) use ($churchIds): void {
                $query
                    ->whereIn('church_id', $churchIds)
                    ->where('status', ChurchMembershipStatus::Active->value);
            });
        } else {
            $readerBaseQuery->whereRaw('1 = 0');
        }
        $readerCounts = [
            'day' => (int) (clone $readerBaseQuery)
                ->where('updated_at', '>=', now()->startOfDay())
                ->distinct()
                ->count('person_id'),
            'week' => (int) (clone $readerBaseQuery)
                ->where('updated_at', '>=', now()->startOfWeek())
                ->distinct()
                ->count('person_id'),
            'year' => (int) (clone $readerBaseQuery)
                ->where('updated_at', '>=', now()->startOfYear())
                ->distinct()
                ->count('person_id'),
        ];

        $importantEvent = Schema::hasColumn('ministry_events', 'is_important')
            ? MinistryEvent::query()
                ->where('is_important', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()->utc())
                ->where('ends_at', '>=', now()->utc())
                ->orderBy('starts_at')
                ->first(['public_id', 'name', 'starts_at', 'ends_at'])
            : null;

        $importantEventPayload = $importantEvent === null ? null : [
            'id' => $importantEvent->public_id,
            'name' => $importantEvent->name,
            'starts_at' => $importantEvent->starts_at?->utc()->toIso8601String(),
            'ends_at' => $importantEvent->ends_at?->utc()->toIso8601String(),
        ];

        return ApiResponse::success($request, [
            'profile' => CurrentUserResource::make($user)->resolve($request),
            'unread_notification_count' => $unreadNotificationCount,
            'recent_payment_intents' => UserPaymentIntentResource::collection($recentIntents)->resolve($request),
            'open_prayer_count' => $openPrayerCount,
            'upcoming_note' => $importantEvent?->name,
            'important_event' => $importantEventPayload,
            'bible_reader_counts' => $readerCounts,
        ]);
    }
}
