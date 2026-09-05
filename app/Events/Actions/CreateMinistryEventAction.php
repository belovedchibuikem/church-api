<?php

namespace App\Events\Actions;

use App\Models\Location;
use App\Models\MinistryEvent;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateMinistryEventAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{
     *     location?: Location|null,
     *     categoryCode: string,
     *     name: string,
     *     startsAt: CarbonImmutable,
     *     endsAt: CarbonImmutable,
     *     registrationOpensAt?: CarbonImmutable|null,
     *     registrationClosesAt?: CarbonImmutable|null,
     *     feeAmountMinor?: int|null,
     *     feeCurrency?: string|null,
     *     capacity?: int|null,
     *     isImportant?: bool|null,
     *     publishedAt?: CarbonImmutable|null
     * }  $attributes
     */
    public function handle(array $attributes, User $actor): MinistryEvent
    {
        $name = Str::squish($attributes['name']);
        $categoryCode = Str::squish($attributes['categoryCode']);

        if ($name === '' || Str::length($name) > 191) {
            throw new InvalidArgumentException('Event names must contain between 1 and 191 characters.');
        }

        if (
            $categoryCode === ''
            || Str::length($categoryCode) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $categoryCode)
        ) {
            throw new InvalidArgumentException('Event category codes must be stable lowercase identifiers.');
        }

        if ($attributes['endsAt']->lt($attributes['startsAt'])) {
            throw new InvalidArgumentException('Event end times must be on or after the start time.');
        }

        return DB::transaction(function () use ($attributes, $name, $categoryCode, $actor): MinistryEvent {
            $location = $attributes['location'] ?? null;
            $lockedLocation = $location === null
                ? null
                : Location::query()->lockForUpdate()->findOrFail($location->getKey());

            $data = [
                'location_id' => $lockedLocation?->getKey(),
                'category_code' => $categoryCode,
                'name' => $name,
                'starts_at' => $attributes['startsAt'],
                'ends_at' => $attributes['endsAt'],
                'registration_opens_at' => $attributes['registrationOpensAt'] ?? null,
                'registration_closes_at' => $attributes['registrationClosesAt'] ?? null,
                'fee_amount_minor' => $attributes['feeAmountMinor'] ?? null,
                'fee_currency' => $attributes['feeCurrency'] ?? null,
                'capacity' => $attributes['capacity'] ?? null,
                'published_at' => $attributes['publishedAt'] ?? null,
            ];
            if (Schema::hasColumn('ministry_events', 'is_important')) {
                $data['is_important'] = (bool) ($attributes['isImportant'] ?? false);
            }

            $event = MinistryEvent::query()->create($data);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'events.event.created',
                actor: $actor,
                targetType: 'ministry_event',
                targetId: $event->public_id,
                metadata: [
                    'category_code' => $categoryCode,
                    'location_id' => $lockedLocation?->public_id,
                ],
            ));

            return $event;
        }, attempts: 3);
    }
}
