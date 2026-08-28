<?php

namespace App\Support\Identity;

use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Throwable;

class RegisterBrowserUserAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param array{
     *     profile: array{
     *         given_name: string,
     *         middle_name?: string|null,
     *         family_name: string,
     *         preferred_name?: string|null,
     *         country?: string|null,
     *         region?: string|null,
     *         locality?: string|null
     *     },
     *     email: string,
     *     password: string,
     *     password_confirmation: string
     * } $attributes
     */
    public function handle(#[\SensitiveParameter] array $attributes): User
    {
        $user = DB::transaction(function () use ($attributes): User {
            $person = Person::query()->create();
            $person->profile()->create([
                'given_name' => trim($attributes['profile']['given_name']),
                'middle_name' => $this->nullableName($attributes['profile']['middle_name'] ?? null),
                'family_name' => trim($attributes['profile']['family_name']),
                'preferred_name' => $this->nullableName($attributes['profile']['preferred_name'] ?? null),
                'country' => $this->nullableName($attributes['profile']['country'] ?? null),
                'region' => $this->nullableName($attributes['profile']['region'] ?? null),
                'locality' => $this->nullableName($attributes['profile']['locality'] ?? null),
            ]);

            $displayName = $this->nullableName($attributes['profile']['preferred_name'] ?? null)
                ?? trim($attributes['profile']['given_name'].' '.$attributes['profile']['family_name']);

            $user = User::query()->create([
                'name' => $displayName,
                'email' => mb_strtolower(trim($attributes['email'])),
                'password' => $attributes['password'],
            ]);
            $user->person()->associate($person);
            $user->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.user.registered',
                actor: $user,
                targetType: 'person',
                targetId: $person->public_id,
            ));

            return $user->load('person.profile');
        }, attempts: 3);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
        }

        return $user;
    }

    private function nullableName(?string $name): ?string
    {
        $trimmedName = $name === null ? null : trim($name);

        return $trimmedName === '' ? null : $trimmedName;
    }
}
