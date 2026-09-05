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

class UpdateMinistryEventAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(MinistryEvent $event, array $attributes, User $actor): MinistryEvent
    {
        return DB::transaction(function () use ($event, $attributes, $actor): MinistryEvent {
            $locked = MinistryEvent::query()->lockForUpdate()->findOrFail($event->getKey());

            if (array_key_exists('name', $attributes) && $attributes['name'] !== null) {
                $name = Str::squish((string) $attributes['name']);
                if ($name === '' || Str::length($name) > 191) {
                    throw new InvalidArgumentException('Event names must contain between 1 and 191 characters.');
                }
                $locked->name = $name;
            }

            if (array_key_exists('category_code', $attributes) && $attributes['category_code'] !== null) {
                $categoryCode = Str::squish((string) $attributes['category_code']);
                if (
                    $categoryCode === ''
                    || Str::length($categoryCode) > 100
                    || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $categoryCode)
                ) {
                    throw new InvalidArgumentException('Event category codes must be stable lowercase identifiers.');
                }
                $locked->category_code = $categoryCode;
            }

            if (array_key_exists('location', $attributes)) {
                $location = $attributes['location'];
                $locked->location_id = $location === null
                    ? null
                    : Location::query()->lockForUpdate()->findOrFail($location->getKey())->getKey();
            }

            foreach (['starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at', 'published_at'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $locked->{$field} = $attributes[$field];
                }
            }

            $numericAndBooleanFields = ['fee_amount_minor', 'capacity'];
            if (Schema::hasColumn('ministry_events', 'is_important')) {
                $numericAndBooleanFields[] = 'is_important';
            }
            foreach ($numericAndBooleanFields as $field) {
                if (array_key_exists($field, $attributes)) {
                    $locked->{$field} = $attributes[$field];
                }
            }

            if (array_key_exists('fee_currency', $attributes)) {
                $locked->fee_currency = $attributes['fee_currency'];
            }

            if ($locked->ends_at !== null && $locked->starts_at !== null && $locked->ends_at->lt($locked->starts_at)) {
                throw new InvalidArgumentException('Event end times must be on or after the start time.');
            }

            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'events.event.updated',
                actor: $actor,
                targetType: 'ministry_event',
                targetId: $locked->public_id,
                metadata: [
                    'category_code' => $locked->category_code,
                    'location_id' => $locked->location?->public_id,
                ],
            ));

            return $locked->fresh(['location:id,public_id,name']) ?? $locked;
        }, attempts: 3);
    }
}
