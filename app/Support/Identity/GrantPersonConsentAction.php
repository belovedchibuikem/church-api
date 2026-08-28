<?php

namespace App\Support\Identity;

use App\Exceptions\ConsentConflictException;
use App\Models\Person;
use App\Models\PersonConsent;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GrantPersonConsentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        Person $person,
        string $purpose,
        string $policyVersion,
        string $source,
        ?User $actor = null,
    ): PersonConsent {
        $this->validateCode($purpose, 'purpose');
        $this->validateVersion($policyVersion);
        $this->validateCode($source, 'source');

        return DB::transaction(function () use (
            $person,
            $purpose,
            $policyVersion,
            $source,
            $actor,
        ): PersonConsent {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $currentConsent = PersonConsent::query()
                ->whereBelongsTo($lockedPerson)
                ->where('purpose', $purpose)
                ->whereNull('withdrawn_at')
                ->lockForUpdate()
                ->first();

            if ($currentConsent !== null) {
                if (
                    $currentConsent->policy_version === $policyVersion
                    && $currentConsent->source === $source
                ) {
                    return $currentConsent;
                }

                throw new ConsentConflictException(
                    'An active consent already exists for this purpose.',
                );
            }

            $consent = $lockedPerson->consents()->create([
                'purpose' => $purpose,
                'policy_version' => $policyVersion,
                'source' => $source,
                'granted_at' => now()->utc(),
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'privacy.consent.granted',
                actor: $actor,
                targetType: 'person_consent',
                targetId: $consent->public_id,
                metadata: [
                    'purpose' => $purpose,
                    'policy_version' => $policyVersion,
                    'source' => $source,
                ],
            ));

            return $consent;
        }, attempts: 3);
    }

    private function validateCode(string $value, string $name): void
    {
        if (
            Str::length($value) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $value)
        ) {
            throw new InvalidArgumentException("The consent {$name} must be a stable lowercase code.");
        }
    }

    private function validateVersion(string $policyVersion): void
    {
        if (
            Str::length($policyVersion) > 100
            || ! Str::isMatch('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $policyVersion)
        ) {
            throw new InvalidArgumentException('The consent policy version is invalid.');
        }
    }
}
