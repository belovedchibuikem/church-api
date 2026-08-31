<?php

namespace App\Support\Church;

use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateHomeChurchAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(HomeChurch $homeChurch, string $name, Person $leader, User $actor): HomeChurch
    {
        $normalizedName = Str::squish($name);
        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Home church names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use ($homeChurch, $normalizedName, $leader, $actor): HomeChurch {
            $locked = HomeChurch::query()->lockForUpdate()->findOrFail($homeChurch->getKey());
            $from = ['name' => $locked->name, 'leader_person_id' => $locked->leader_person_id];
            $duplicate = HomeChurch::query()
                ->where('location_id', $locked->location_id)
                ->where('name', $normalizedName)
                ->whereKeyNot($locked->getKey())
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw new InvalidArgumentException('A home church with this name already exists at the location.');
            }
            $locked->name = $normalizedName;
            $locked->leader_person_id = $leader->getKey();
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'home_church.updated',
                actor: $actor,
                targetType: 'home_church',
                targetId: $locked->public_id,
                scopeType: 'home_church',
                scopeId: $locked->public_id,
                metadata: ['from' => $from, 'to' => ['name' => $locked->name, 'leader_person_id' => $leader->public_id]],
            ));

            return $locked;
        }, attempts: 3);
    }
}
