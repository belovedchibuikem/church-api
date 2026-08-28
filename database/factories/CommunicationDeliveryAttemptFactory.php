<?php

namespace Database\Factories;

use App\Communication\CommunicationChannel;
use App\Communication\CommunicationDeliveryStatus;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationDeliveryAttempt>
 */
class CommunicationDeliveryAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'communication_recipient_id' => CommunicationRecipient::factory(),
            'channel' => CommunicationChannel::Email,
            'status' => CommunicationDeliveryStatus::Suppressed,
            'result_code' => 'provider_unconfigured',
            'idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
            'attempted_at' => now(),
        ];
    }
}
