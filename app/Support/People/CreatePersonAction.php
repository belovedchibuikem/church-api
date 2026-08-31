<?php

namespace App\Support\People;

use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreatePersonAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{given_name: string, family_name: string, preferred_name?: string|null, phone?: string|null, email?: string|null}  $attributes
     */
    public function handle(array $attributes, ?User $actor = null): Person
    {
        $given = Str::squish($attributes['given_name']);
        $family = Str::squish($attributes['family_name']);
        if ($given === '' || $family === '') {
            throw new InvalidArgumentException('Given name and family name are required.');
        }

        return DB::transaction(function () use ($given, $family, $attributes, $actor): Person {
            $person = Person::query()->create();
            $person->profile()->create([
                'given_name' => $given,
                'family_name' => $family,
                'preferred_name' => isset($attributes['preferred_name']) ? Str::squish((string) $attributes['preferred_name']) ?: null : null,
                'phone' => $attributes['phone'] ?? null,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'people.created',
                actor: $actor,
                targetType: 'person',
                targetId: $person->public_id,
                metadata: [
                    'email' => $attributes['email'] ?? null,
                ],
            ));

            return $person->fresh(['profile', 'user']) ?? $person;
        }, attempts: 3);
    }
}
