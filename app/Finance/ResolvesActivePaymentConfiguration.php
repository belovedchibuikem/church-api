<?php

namespace App\Finance;

use App\Models\PaymentProviderConfiguration;

class ResolvesActivePaymentConfiguration
{
    public function active(): ?PaymentProviderConfiguration
    {
        $configuration = PaymentProviderConfiguration::query()
            ->where('is_active', true)
            ->first();

        if ($configuration === null || ! $configuration->credentialsConfigured()) {
            return null;
        }

        return $configuration;
    }
}
