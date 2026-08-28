<?php

namespace Database\Factories;

use App\Communication\CommunicationRecipientStatus;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationRecipient>
 */
class CommunicationRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'communication_broadcast_id' => CommunicationBroadcast::factory(),
            'user_id' => User::factory()->withPerson(),
            'person_id' => fn (array $attributes): int => User::query()
                ->findOrFail($attributes['user_id'])
                ->person_id,
            'status' => CommunicationRecipientStatus::Eligible,
            'reason_code' => 'eligible',
            'resolved_at' => now(),
        ];
    }
}
