<?php

namespace App\AdvisoryAi;

use App\AdvisoryAi\Contracts\AdvisoryAiProvider;

final class DisabledAdvisoryAiProvider implements AdvisoryAiProvider
{
    public function advise(AdvisoryRequest $request): AdvisoryResponse
    {
        return AdvisoryResponse::unavailable();
    }
}
