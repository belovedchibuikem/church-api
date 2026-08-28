<?php

namespace Database\Factories;

use App\Models\AccessDecision;
use App\Models\User;
use App\Support\Authorization\AccessDecisionReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessDecision>
 */
class AccessDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'matched_role_assignment_id' => null,
            'permission_code' => 'testing.permission.view',
            'scope_type' => 'church',
            'scope_key' => fake()->uuid(),
            'allowed' => false,
            'reason_code' => AccessDecisionReason::PermissionNotAssigned->value,
            'correlation_id' => fake()->uuid(),
            'decided_at' => now(),
        ];
    }
}
