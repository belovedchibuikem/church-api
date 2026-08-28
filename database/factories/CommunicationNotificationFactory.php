<?php

namespace Database\Factories;

use App\Models\CommunicationNotification;
use App\Models\CommunicationRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationNotification>
 */
class CommunicationNotificationFactory extends Factory
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
            'person_id' => fn (array $attributes): int => CommunicationRecipient::query()
                ->findOrFail($attributes['communication_recipient_id'])
                ->person_id,
            'user_id' => fn (array $attributes): int => CommunicationRecipient::query()
                ->findOrFail($attributes['communication_recipient_id'])
                ->user_id,
            'read_at' => null,
        ];
    }
}
