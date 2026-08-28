<?php

namespace App\Support\Identity;

use App\Models\Person;
use App\Models\PersonPreference;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdatePersonPreferencesAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<int, string>  $notificationChannels
     */
    public function handle(
        Person $person,
        string $locale,
        string $timezone,
        array $notificationChannels,
        ?User $actor = null,
    ): PersonPreference {
        $normalizedChannels = $this->validateAndNormalize($locale, $timezone, $notificationChannels);

        return DB::transaction(function () use (
            $person,
            $locale,
            $timezone,
            $normalizedChannels,
            $actor,
        ): PersonPreference {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $preference = PersonPreference::query()
                ->whereBelongsTo($lockedPerson)
                ->lockForUpdate()
                ->first();
            $values = [
                'locale' => $locale,
                'timezone' => $timezone,
                'notification_channels' => $normalizedChannels,
            ];

            if ($preference === null) {
                $preference = $lockedPerson->preference()->create($values);
                $changedFields = collect($values)->keys()->all();
            } else {
                $preference->fill($values);

                if (! $preference->isDirty()) {
                    return $preference;
                }

                $changedFields = collect($preference->getDirty())->keys()->all();
                $preference->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.preferences.updated',
                actor: $actor,
                targetType: 'person_preference',
                targetId: $preference->public_id,
                metadata: ['changed_fields' => $changedFields],
            ));

            return $preference;
        }, attempts: 3);
    }

    /**
     * @param  array<int, string>  $notificationChannels
     * @return array<int, string>
     */
    private function validateAndNormalize(
        string $locale,
        string $timezone,
        array $notificationChannels,
    ): array {
        if (! Str::isMatch('/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/', $locale)) {
            throw new InvalidArgumentException('The locale must be a valid language tag.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('The timezone must be a recognized IANA identifier.');
        }

        if (count($notificationChannels) > 20) {
            throw new InvalidArgumentException('No more than 20 notification channels may be selected.');
        }

        foreach ($notificationChannels as $channel) {
            if (
                ! is_string($channel)
                || Str::length($channel) > 50
                || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $channel)
            ) {
                throw new InvalidArgumentException('Every notification channel must be a stable lowercase code.');
            }
        }

        return collect($notificationChannels)->unique()->values()->all();
    }
}
