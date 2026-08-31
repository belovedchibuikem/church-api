<?php

namespace App\Mission\Actions;

use App\Mission\CrusadeStatus;
use App\Models\Crusade;
use App\Models\Location;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateCrusadeAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Crusade $crusade, array $attributes, User $actor): Crusade
    {
        return DB::transaction(function () use ($crusade, $attributes, $actor): Crusade {
            $locked = Crusade::query()->lockForUpdate()->findOrFail($crusade->getKey());
            $status = $locked->status instanceof CrusadeStatus ? $locked->status : CrusadeStatus::from((string) $locked->status);
            if (in_array($status, [CrusadeStatus::Archived, CrusadeStatus::Cancelled, CrusadeStatus::Closed], true)) {
                throw new InvalidArgumentException('Archived, cancelled, or closed crusades cannot be edited.');
            }

            if (array_key_exists('name', $attributes) && $attributes['name'] !== null) {
                $name = Str::squish((string) $attributes['name']);
                if ($name === '' || Str::length($name) > 191) {
                    throw new InvalidArgumentException('Crusade names must contain between 1 and 191 characters.');
                }
                $locked->name = $name;
            }

            foreach (['code' => 50, 'theme' => 191, 'purpose' => 500, 'description' => 10000, 'timezone' => 64] as $field => $max) {
                if (array_key_exists($field, $attributes)) {
                    $value = $attributes[$field];
                    $locked->{$field} = $value === null || $value === '' ? null : Str::squish((string) $value);
                    if ($locked->{$field} !== null && Str::length((string) $locked->{$field}) > $max) {
                        throw new InvalidArgumentException("Field {$field} must contain at most {$max} characters.");
                    }
                }
            }

            if (array_key_exists('location_id', $attributes)) {
                $locked->location_id = $attributes['location_id'] === null
                    ? null
                    : Location::query()->lockForUpdate()->findOrFail($attributes['location_id'])->getKey();
            }
            foreach (['starts_at', 'ends_at'] as $dateField) {
                if (array_key_exists($dateField, $attributes)) {
                    $locked->{$dateField} = $attributes[$dateField];
                }
            }
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.crusade.updated',
                actor: $actor,
                targetType: 'crusade',
                targetId: $locked->public_id,
                scopeType: 'crusade',
                scopeId: $locked->public_id,
            ));

            return $locked->fresh(['location:id,public_id,name']) ?? $locked;
        }, attempts: 3);
    }
}
