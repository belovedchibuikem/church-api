<?php

namespace App\Events\Actions;

use App\Events\EventRegistrationStatus;
use App\Models\EventRegistration;
use App\Models\MinistryEvent;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RegisterForEventAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(MinistryEvent $event, Person $person, string $idempotencyKey, ?User $actor = null): EventRegistration
    {
        $key = trim($idempotencyKey);
        if (Str::length($key) < 8 || Str::length($key) > 191) {
            throw new InvalidArgumentException('Event registration idempotency keys must contain 8 to 191 characters.');
        }
        $scopeHash = hash_hmac('sha256', "event.register|{$event->getKey()}|{$key}", (string) config('app.key'));

        return DB::transaction(function () use ($event, $person, $actor, $scopeHash): EventRegistration {
            $retry = EventRegistration::query()->lockForUpdate()->where('idempotency_scope_hash', $scopeHash)->first();
            if ($retry !== null) {
                if ($retry->ministry_event_id !== $event->getKey() || $retry->person_id !== $person->getKey()) {
                    throw new DomainException('Event registration idempotency key conflict.');
                }

                return $retry;
            }
            $lockedEvent = MinistryEvent::query()->lockForUpdate()->findOrFail($event->getKey());
            if (($lockedEvent->registration_opens_at?->isFuture() ?? false) || ($lockedEvent->registration_closes_at?->isPast() ?? false)) {
                throw new DomainException('Event registration is not open.');
            }
            if ($lockedEvent->capacity !== null && EventRegistration::query()->whereBelongsTo($lockedEvent, 'event')->count() >= $lockedEvent->capacity) {
                throw new DomainException('Event capacity has been reached.');
            }
            $existing = EventRegistration::query()->whereBelongsTo($lockedEvent, 'event')->whereBelongsTo($person)->first();
            if ($existing !== null) {
                return $existing;
            }
            $requiresPayment = ($lockedEvent->fee_amount_minor ?? 0) > 0;
            $registration = new EventRegistration;
            $registration->forceFill(['ministry_event_id' => $lockedEvent->getKey(), 'person_id' => $person->getKey(), 'status' => $requiresPayment ? EventRegistrationStatus::PaymentPending : EventRegistrationStatus::Confirmed, 'idempotency_scope_hash' => $scopeHash, 'registered_at' => now()->utc(), 'confirmed_at' => $requiresPayment ? null : now()->utc()])->save();
            $this->recordAuditEvent->handle(new AuditEventData(action: 'events.registration.created', actor: $actor, targetType: 'event_registration', targetId: $registration->public_id, scopeType: 'ministry_event', scopeId: $lockedEvent->public_id, metadata: ['payment_required' => $requiresPayment]));

            return $registration;
        }, attempts: 3);
    }
}
