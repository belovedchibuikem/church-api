<?php

namespace App\Mission\Actions;

use App\Exceptions\MissionInvalidTransitionException;
use App\Mission\CrusadeStatus;
use App\Models\Crusade;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransitionCrusadeAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Crusade $crusade, CrusadeStatus $target, ?string $reasonCode, User $actor): Crusade
    {
        $this->assertSafeCode($reasonCode);

        return DB::transaction(function () use ($crusade, $target, $reasonCode, $actor): Crusade {
            $locked = Crusade::query()->lockForUpdate()->findOrFail($crusade->getKey());
            $source = $locked->status instanceof CrusadeStatus ? $locked->status : CrusadeStatus::from((string) $locked->status);

            if ($source === $target) {
                return $locked;
            }

            if (! in_array($target, $source->allowedTargets(), true)) {
                throw new MissionInvalidTransitionException(
                    "Crusade cannot transition from {$source->value} to {$target->value}.",
                );
            }

            if ($target->requiresReason() && ($reasonCode === null || $reasonCode === '')) {
                throw new InvalidArgumentException('A reason_code is required for this crusade transition.');
            }

            $locked->status = $target;
            if ($target->isPubliclyVisible() && $locked->published_at === null) {
                $locked->published_at = now()->utc();
            }
            if ($target === CrusadeStatus::Archived) {
                $locked->archived_at = now()->utc();
                $locked->archive_reason_code = $reasonCode;
            }
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.crusade.transitioned',
                actor: $actor,
                targetType: 'crusade',
                targetId: $locked->public_id,
                scopeType: 'crusade',
                scopeId: $locked->public_id,
                metadata: array_filter([
                    'from_status' => $source->value,
                    'to_status' => $target->value,
                    'reason_code' => $reasonCode,
                ]),
            ));

            return $locked;
        }, attempts: 3);
    }

    private function assertSafeCode(?string $code): void
    {
        if ($code !== null && (Str::length($code) > 100 || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $code))) {
            throw new InvalidArgumentException('Mission reason codes must be stable lowercase identifiers.');
        }
    }
}
