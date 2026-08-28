<?php

namespace App\AdvisoryAi\Contracts;

use App\AdvisoryAi\AdvisoryRequest;
use App\AdvisoryAi\AdvisoryResponse;

interface AdvisoryAiProvider
{
    /**
     * The request has already passed the platform data-governance boundary.
     */
    public function advise(AdvisoryRequest $request): AdvisoryResponse;
}
