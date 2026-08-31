<?php

namespace App\Support\Church;

use App\Models\Church;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateChurchStatusAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Church $church, string $status, string $reason, User $actor): Church
    {
        $notes = trim($reason);
        if ($notes === '') {
            throw new InvalidArgumentException('A reason is required to change church status.');
        }

        $target = match ($status) {
            'active', 'published' => 'active',
            'unpublished', 'suspended', 'closed' => $status === 'unpublished' ? 'unpublished' : $status,
            default => throw new InvalidArgumentException('Unsupported church status.'),
        };

        return DB::transaction(function () use ($church, $target, $notes, $actor): Church {
            $locked = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $from = $locked->published_at ? 'active' : 'unpublished';
            if ($from === $target) {
                return $locked;
            }

            if ($target === 'closed' && (
                $locked->homeChurches()->exists()
                || $locked->memberships()->exists()
                || $locked->firstTimers()->exists()
            )) {
                throw new InvalidArgumentException(
                    'This church cannot be closed while memberships, first-timers, or home churches remain. Reassign them first, or unpublish instead.',
                );
            }

            $locked->published_at = $target === 'active' ? now()->utc() : null;
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.status_changed',
                actor: $actor,
                targetType: 'church',
                targetId: $locked->public_id,
                scopeType: 'church',
                scopeId: $locked->public_id,
                metadata: [
                    'from_status' => $from,
                    'to_status' => $target,
                    'reason' => $notes,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
