<?php

namespace App\Support\Platform;

use App\Models\FeatureFlag;
use App\Models\User;

class DisableFeatureFlagAction
{
    public function __construct(private SetFeatureFlagStateAction $setState) {}

    public function handle(FeatureFlag $flag, User $actor): FeatureFlag
    {
        return $this->setState->handle($flag, false, $actor);
    }
}
