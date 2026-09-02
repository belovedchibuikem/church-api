<?php

namespace App\Support\Kca;

use App\Models\KcaCohort;
use App\Models\KcaOrientationSession;
use App\Models\Location;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateKcaOrientationSessionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{
     *     cohort?: KcaCohort|null,
     *     location?: Location|null,
     *     name: string,
     *     venueLabel?: string|null,
     *     startsAt: CarbonImmutable,
     *     endsAt?: CarbonImmutable|null,
     *     capacity?: int|null,
     *     notes?: string|null,
     *     publishedAt?: CarbonImmutable|null
     * }  $attributes
     */
    public function handle(array $attributes, User $actor): KcaOrientationSession
    {
        $name = Str::squish($attributes['name']);
        if ($name === '' || Str::length($name) > 191) {
            throw new InvalidArgumentException('Orientation session names must contain between 1 and 191 characters.');
        }

        $endsAt = $attributes['endsAt'] ?? null;
        if ($endsAt !== null && $endsAt->lt($attributes['startsAt'])) {
            throw new InvalidArgumentException('Orientation session end times must be on or after the start time.');
        }

        return DB::transaction(function () use ($attributes, $name, $endsAt, $actor): KcaOrientationSession {
            $cohort = $attributes['cohort'] ?? null;
            $location = $attributes['location'] ?? null;
            $lockedCohort = $cohort === null
                ? null
                : KcaCohort::query()->lockForUpdate()->findOrFail($cohort->getKey());
            $lockedLocation = $location === null
                ? null
                : Location::query()->lockForUpdate()->findOrFail($location->getKey());

            $session = KcaOrientationSession::query()->create([
                'kca_cohort_id' => $lockedCohort?->getKey(),
                'location_id' => $lockedLocation?->getKey(),
                'name' => $name,
                'venue_label' => isset($attributes['venueLabel']) ? Str::squish((string) $attributes['venueLabel']) ?: null : null,
                'starts_at' => $attributes['startsAt'],
                'ends_at' => $endsAt,
                'capacity' => $attributes['capacity'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'published_at' => $attributes['publishedAt'] ?? null,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.orientation_session.created',
                actor: $actor,
                targetType: 'kca_orientation_session',
                targetId: $session->public_id,
                metadata: [
                    'cohort_id' => $lockedCohort?->public_id,
                    'location_id' => $lockedLocation?->public_id,
                ],
            ));

            return $session;
        }, attempts: 3);
    }
}
