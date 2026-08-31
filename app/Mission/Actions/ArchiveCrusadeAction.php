<?php

namespace App\Mission\Actions;

use App\Mission\CrusadeStatus;
use App\Models\Crusade;
use App\Models\User;
use InvalidArgumentException;

class ArchiveCrusadeAction
{
    public function __construct(private TransitionCrusadeAction $transition) {}

    public function handle(Crusade $crusade, string $reasonCode, User $actor): Crusade
    {
        $status = $crusade->status instanceof CrusadeStatus ? $crusade->status : CrusadeStatus::from((string) $crusade->status);
        if ($status === CrusadeStatus::Archived) {
            return $crusade;
        }

        if (! in_array(CrusadeStatus::Archived, $status->allowedTargets(), true)) {
            throw new InvalidArgumentException(
                'This crusade cannot be archived from its current status. Complete reporting, cancel, or close it first.',
            );
        }

        return $this->transition->handle($crusade, CrusadeStatus::Archived, $reasonCode, $actor);
    }
}
