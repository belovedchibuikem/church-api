<?php

namespace App\Support\Kca;

use App\Models\KcaCohort;
use App\Models\KcaOrientationSession;
use App\Models\Location;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateKcaOrientationSessionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(KcaOrientationSession $session, array $attributes, User $actor): KcaOrientationSession
    {
        return DB::transaction(function () use ($session, $attributes, $actor): KcaOrientationSession {
            $locked = KcaOrientationSession::query()->lockForUpdate()->findOrFail($session->getKey());

            if (array_key_exists('name', $attributes) && $attributes['name'] !== null) {
                $name = Str::squish((string) $attributes['name']);
                if ($name === '' || Str::length($name) > 191) {
                    throw new InvalidArgumentException('Orientation session names must contain between 1 and 191 characters.');
                }
                $locked->name = $name;
            }

            if (array_key_exists('cohort', $attributes)) {
                $cohort = $attributes['cohort'];
                $locked->kca_cohort_id = $cohort === null
                    ? null
                    : KcaCohort::query()->lockForUpdate()->findOrFail($cohort->getKey())->getKey();
            }

            if (array_key_exists('location', $attributes)) {
                $location = $attributes['location'];
                $locked->location_id = $location === null
                    ? null
                    : Location::query()->lockForUpdate()->findOrFail($location->getKey())->getKey();
            }

            if (array_key_exists('venue_label', $attributes)) {
                $label = $attributes['venue_label'];
                $locked->venue_label = $label === null ? null : (Str::squish((string) $label) ?: null);
            }

            foreach (['starts_at', 'ends_at', 'published_at'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $locked->{$field} = $attributes[$field];
                }
            }

            if (array_key_exists('capacity', $attributes)) {
                $locked->capacity = $attributes['capacity'];
            }

            if (array_key_exists('notes', $attributes)) {
                $locked->notes = $attributes['notes'];
            }

            $endsAt = $locked->ends_at ?? $locked->starts_at;
            if ($endsAt !== null && $locked->starts_at !== null && $endsAt->lt($locked->starts_at)) {
                throw new InvalidArgumentException('Orientation session end times must be on or after the start time.');
            }

            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.orientation_session.updated',
                actor: $actor,
                targetType: 'kca_orientation_session',
                targetId: $locked->public_id,
                metadata: [
                    'cohort_id' => $locked->cohort?->public_id,
                    'location_id' => $locked->location?->public_id,
                ],
            ));

            return $locked->fresh(['cohort:id,public_id,name', 'location:id,public_id,name']) ?? $locked;
        }, attempts: 3);
    }
}
