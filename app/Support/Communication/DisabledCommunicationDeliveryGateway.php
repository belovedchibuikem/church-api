<?php

namespace App\Support\Communication;

use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Support\Communication\Contracts\CommunicationDeliveryGateway;

class DisabledCommunicationDeliveryGateway implements CommunicationDeliveryGateway
{
    public function attempt(
        CommunicationRecipient $recipient,
        CommunicationTemplate $template,
        string $idempotencyKey,
    ): CommunicationDeliveryResult {
        return CommunicationDeliveryResult::providerUnconfigured();
    }
}
