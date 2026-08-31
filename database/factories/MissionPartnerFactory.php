<?php

namespace Database\Factories;

use App\Models\MissionPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionPartner>
 */
class MissionPartnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'partner_type' => 'organisation',
            'status' => 'active',
            'geography' => fake()->country(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'notes' => null,
            'archived_at' => null,
        ];
    }
}
