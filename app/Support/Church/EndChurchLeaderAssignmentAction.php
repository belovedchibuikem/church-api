<?php

namespace App\Support\Church;

use App\Models\ChurchRoleAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EndChurchLeaderAssignmentAction
{
    public function __construct(
        private RevokeChurchScopedAdminAccessAction $revokeAdminAccess,
    ) {}

    public function handle(
        ChurchRoleAssignment $assignment,
        ?CarbonInterface $endedAt = null,
        ?User $actor = null,
    ): ChurchRoleAssignment {
        return DB::transaction(function () use ($assignment, $endedAt, $actor): ChurchRoleAssignment {
            $locked = ChurchRoleAssignment::query()
                ->with(['church', 'person.user'])
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());

            if ($locked->role_type !== 'leader') {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'ended',
                'ended_at' => ($endedAt ?? now())->utc(),
            ])->save();

            if ($locked->church !== null && $locked->person !== null) {
                $this->revokeAdminAccess->handle($locked->person, $locked->church, $actor);
            }

            return $locked->fresh(['church', 'person.user', 'department']);
        }, attempts: 3);
    }
}
