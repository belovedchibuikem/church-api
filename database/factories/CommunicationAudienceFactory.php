<?php

namespace Database\Factories;

use App\Models\CommunicationAudience;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationAudience>
 */
class CommunicationAudienceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'audience.'.Str::lower(Str::random(12)),
            'name' => fake()->words(3, true),
            'created_by_user_id' => User::factory(),
        ];
    }
}
