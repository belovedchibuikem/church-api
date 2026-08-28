<?php

namespace App\Support\Communication\Contracts;

use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Support\Communication\CommunicationDeliveryResult;
use App\Support\Communication\DisabledCommunicationDeliveryGateway;
use Illuminate\Container\Attributes\Bind;

#[Bind(DisabledCommunicationDeliveryGateway::class)]
interface CommunicationDeliveryGateway
{
    public function attempt(
        CommunicationRecipient $recipient,
        CommunicationTemplate $template,
        string $idempotencyKey,
    ): CommunicationDeliveryResult;
}
