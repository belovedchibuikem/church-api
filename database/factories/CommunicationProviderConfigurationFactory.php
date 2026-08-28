<?php

namespace Database\Factories;

use App\Models\CommunicationProviderConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationProviderConfiguration>
 */
class CommunicationProviderConfigurationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email_provider' => 'resend',
            'email_sender_name' => 'Family House Connect',
            'email_sender_address' => 'noreply@example.test',
            'email_api_key' => 're_test_'.$this->faker->uuid(),
            'consent_required_channels' => ['email', 'sms', 'whatsapp', 'push'],
            'retry_max_attempts' => 3,
            'retry_backoff_seconds' => 60,
            'is_active' => false,
            'configuration_revision' => 1,
        ];
    }
}
