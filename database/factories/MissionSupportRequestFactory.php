<?php

namespace Database\Factories;

use App\Models\MissionSupportRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionSupportRequest>
 */
class MissionSupportRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requested_by_person_id' => null,
            'crusade_id' => null,
            'title' => fake()->sentence(4),
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'submitted',
            'amount_minor' => null,
            'currency' => null,
            'details' => null,
            'idempotency_key_hash' => null,
        ];
    }
}
