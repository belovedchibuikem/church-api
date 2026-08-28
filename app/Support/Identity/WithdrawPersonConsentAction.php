<?php

namespace App\Support\Identity;

use App\Models\Person;
use App\Models\PersonConsent;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WithdrawPersonConsentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(PersonConsent $consent, string $source, ?User $actor = null): PersonConsent
    {
        $this->validateSource($source);

        return DB::transaction(function () use ($consent, $source, $actor): PersonConsent {
            Person::query()->lockForUpdate()->findOrFail($consent->person_id);
            $lockedConsent = PersonConsent::query()
                ->lockForUpdate()
                ->findOrFail($consent->getKey());

            if ($lockedConsent->isWithdrawn()) {
                return $lockedConsent;
            }

            $lockedConsent->withdrawal_source = $source;
            $lockedConsent->withdrawn_at = now()->utc();
            $lockedConsent->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'privacy.consent.withdrawn',
                actor: $actor,
                targetType: 'person_consent',
                targetId: $lockedConsent->public_id,
                metadata: [
                    'purpose' => $lockedConsent->purpose,
                    'policy_version' => $lockedConsent->policy_version,
                    'source' => $source,
                ],
            ));

            return $lockedConsent;
        }, attempts: 3);
    }

    private function validateSource(string $source): void
    {
        if (
            Str::length($source) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $source)
        ) {
            throw new InvalidArgumentException('The withdrawal source must be a stable lowercase code.');
        }
    }
}
