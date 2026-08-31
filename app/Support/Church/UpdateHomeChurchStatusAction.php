<?php

namespace App\Support\Church;

use App\Church\HomeChurchStatus;
use App\Models\HomeChurch;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateHomeChurchStatusAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(HomeChurch $homeChurch, HomeChurchStatus $status, string $reason, User $actor): HomeChurch
    {
        $notes = trim($reason);
        if ($notes === '') {
            throw new InvalidArgumentException('A reason is required to change home church status.');
        }

        return DB::transaction(function () use ($homeChurch, $status, $notes, $actor): HomeChurch {
            $locked = HomeChurch::query()->lockForUpdate()->findOrFail($homeChurch->getKey());
            $from = $locked->status;
            if ($from === $status) {
                return $locked;
            }

            $allowed = match ($from) {
                HomeChurchStatus::Active => [HomeChurchStatus::Suspended, HomeChurchStatus::Closed],
                HomeChurchStatus::Suspended => [HomeChurchStatus::Active, HomeChurchStatus::Closed],
                HomeChurchStatus::Closed => [HomeChurchStatus::Active],
            };
            if (! in_array($status, $allowed, true)) {
                throw new InvalidArgumentException("Home churches cannot move from {$from->value} to {$status->value}.");
            }

            $locked->status = $status;
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'home_church.status_changed',
                actor: $actor,
                targetType: 'home_church',
                targetId: $locked->public_id,
                scopeType: 'home_church',
                scopeId: $locked->public_id,
                metadata: [
                    'from_status' => $from->value,
                    'to_status' => $status->value,
                    'reason' => $notes,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
