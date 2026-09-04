<?php

namespace App\Support\Kca;

use App\Kca\KcaAssignmentState;
use App\Models\KcaAssignment;
use App\Models\KcaSoulWin;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordKcaSoulWinAction
{
    public function __construct(
        private KcaSoulTreeService $tree,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /**
     * @param  array{given_name: string, family_name?: string|null, phone?: string|null, email?: string|null, notes?: string|null}  $person
     */
    public function handle(KcaAssignment $assignment, ?KcaSoulWin $parent, array $person, User $actor): KcaSoulWin
    {
        $given = Str::squish((string) ($person['given_name'] ?? ''));
        if ($given === '') {
            throw new InvalidArgumentException('A given name is required for each soul won.');
        }

        return DB::transaction(function () use ($assignment, $parent, $person, $given, $actor): KcaSoulWin {
            $locked = KcaAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $lockedParent = $parent === null ? null : KcaSoulWin::query()->lockForUpdate()->findOrFail($parent->getKey());
            $depth = $this->tree->assertCanAdd($locked, $lockedParent);
            $soul = KcaSoulWin::query()->create([
                'kca_assignment_id' => $locked->getKey(),
                'parent_id' => $lockedParent?->getKey(),
                'depth' => $depth,
                'given_name' => $given,
                'family_name' => isset($person['family_name']) ? Str::squish((string) $person['family_name']) : null,
                'phone' => isset($person['phone']) ? Str::squish((string) $person['phone']) : null,
                'email' => isset($person['email']) ? Str::squish((string) $person['email']) : null,
                'notes' => isset($person['notes']) ? (string) $person['notes'] : null,
                'won_at' => now()->utc(),
            ]);

            $complete = $this->tree->isComplete($locked->fresh());
            if (
                $complete
                && in_array($locked->state, [KcaAssignmentState::Assigned, KcaAssignmentState::Resubmit], true)
                && $locked->evidenceSubmissions()->exists()
            ) {
                app(KcaAssignmentTransitionService::class)
                    ->assertCanTransition($locked->state, KcaAssignmentState::Submitted);
                $locked->state = KcaAssignmentState::Submitted;
                $locked->submitted_at = now()->utc();
                $locked->last_transitioned_by_user_id = $actor->getKey();
                $locked->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.soul_win.recorded',
                actor: $actor,
                targetType: 'kca_soul_win',
                targetId: $soul->public_id,
                metadata: [
                    'assignment_id' => $locked->public_id,
                    'depth' => $depth,
                    'complete' => $complete,
                ],
            ));

            return $soul;
        }, attempts: 3);
    }
}
