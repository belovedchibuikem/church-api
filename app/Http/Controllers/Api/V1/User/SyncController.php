<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\UpdateSyncCheckpointRequest;
use App\Models\CommunicationNotification;
use App\Models\PastoralNeed;
use App\Models\PaymentIntent;
use App\Models\PrayerRequest;
use App\Models\SyncCheckpoint;
use App\Support\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function checkpoint(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $checkpoint = SyncCheckpoint::query()->where('person_id', $person->getKey())->first();

        return ApiResponse::success($request, [
            'cursor' => $checkpoint?->cursor,
            'updated_at' => $checkpoint?->updated_at?->toIso8601String(),
        ]);
    }

    public function updateCheckpoint(UpdateSyncCheckpointRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $cursor = (string) $request->validated('cursor');

        $checkpoint = SyncCheckpoint::query()->firstOrNew(['person_id' => $person->getKey()]);
        $checkpoint->forceFill([
            'cursor' => $cursor,
            'updated_at' => now(),
        ])->save();

        return ApiResponse::success($request, [
            'cursor' => $checkpoint->cursor,
            'updated_at' => $checkpoint->updated_at?->toIso8601String(),
        ]);
    }

    public function changes(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $user = $this->actor($request);
        $sinceRaw = (string) $request->query('since', '');
        abort_unless($sinceRaw !== '', 422, 'The since cursor is required.');

        try {
            $since = CarbonImmutable::parse($sinceRaw)->utc();
        } catch (\Throwable) {
            abort(422, 'The since cursor must be a valid ISO8601 timestamp.');
        }

        $changes = collect();

        $prayers = PrayerRequest::query()
            ->where('person_id', $person->getKey())
            ->where('created_at', '>', $since)
            ->orderBy('created_at')
            ->limit(100)
            ->get(['public_id', 'created_at', 'updated_at']);

        foreach ($prayers as $prayer) {
            $changes->push([
                'type' => 'prayer_request',
                'id' => $prayer->public_id,
                'updated_at' => ($prayer->updated_at ?? $prayer->created_at)?->toIso8601String(),
            ]);
        }

        $needs = PastoralNeed::query()
            ->where('person_id', $person->getKey())
            ->where('created_at', '>', $since)
            ->orderBy('created_at')
            ->limit(100)
            ->get(['public_id', 'created_at', 'updated_at']);

        foreach ($needs as $need) {
            $changes->push([
                'type' => 'pastoral_need',
                'id' => $need->public_id,
                'updated_at' => ($need->updated_at ?? $need->created_at)?->toIso8601String(),
            ]);
        }

        $notifications = CommunicationNotification::query()
            ->where(function ($query) use ($user, $person): void {
                $query->where('user_id', $user->getKey())
                    ->orWhere('person_id', $person->getKey());
            })
            ->where('created_at', '>', $since)
            ->orderBy('created_at')
            ->limit(100)
            ->get(['public_id', 'created_at', 'updated_at']);

        foreach ($notifications as $notification) {
            $changes->push([
                'type' => 'notification',
                'id' => $notification->public_id,
                'updated_at' => ($notification->updated_at ?? $notification->created_at)?->toIso8601String(),
            ]);
        }

        $intents = PaymentIntent::query()
            ->where('payer_person_id', $person->getKey())
            ->where('created_at', '>', $since)
            ->orderBy('created_at')
            ->limit(100)
            ->get(['public_id', 'created_at', 'updated_at']);

        foreach ($intents as $intent) {
            $changes->push([
                'type' => 'payment_intent',
                'id' => $intent->public_id,
                'updated_at' => ($intent->updated_at ?? $intent->created_at)?->toIso8601String(),
            ]);
        }

        $sorted = $changes
            ->sortBy('updated_at')
            ->values();

        $nextCursor = $sorted->isEmpty()
            ? $since->toIso8601String()
            : (string) $sorted->last()['updated_at'];

        return ApiResponse::success($request, [
            'changes' => $sorted->all(),
            'next_cursor' => $nextCursor,
        ]);
    }
}
