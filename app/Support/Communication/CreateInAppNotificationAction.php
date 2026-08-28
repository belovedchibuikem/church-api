<?php

namespace App\Support\Communication;

use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationRecipientStatus;
use App\Exceptions\CommunicationConsentDeniedException;
use App\Exceptions\CommunicationInvalidStateException;
use App\Models\CommunicationNotification;
use App\Models\CommunicationRecipient;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class CreateInAppNotificationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(CommunicationRecipient $recipient, User $actor): CommunicationNotification
    {
        return DB::transaction(function () use ($recipient, $actor): CommunicationNotification {
            $lockedRecipient = CommunicationRecipient::query()
                ->with('broadcast')
                ->lockForUpdate()
                ->findOrFail($recipient->getKey());

            if ($lockedRecipient->status !== CommunicationRecipientStatus::Eligible) {
                throw new CommunicationConsentDeniedException('Suppressed recipients cannot receive in-app notifications.');
            }

            if (
                $lockedRecipient->broadcast->status !== CommunicationBroadcastStatus::Prepared
                || $lockedRecipient->broadcast->channel !== CommunicationChannel::InApp
            ) {
                throw new CommunicationInvalidStateException('An eligible recipient of a prepared in-app broadcast is required.');
            }

            $existing = CommunicationNotification::query()
                ->whereBelongsTo($lockedRecipient, 'recipient')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $notification = (new CommunicationNotification)->forceFill([
                'communication_recipient_id' => $lockedRecipient->getKey(),
                'person_id' => $lockedRecipient->person_id,
                'user_id' => $lockedRecipient->user_id,
                'read_at' => null,
            ]);
            $notification->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'communications.notification.created',
                actor: $actor,
                targetType: 'communication_notification',
                targetId: $notification->public_id,
                metadata: ['channel' => CommunicationChannel::InApp->value],
            ));

            return $notification;
        }, attempts: 3);
    }
}
