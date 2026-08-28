<?php

namespace Database\Factories;

use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScopeAssignment>
 */
class ScopeAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_assignment_id' => RoleAssignment::factory(),
            'assigned_by_user_id' => null,
            'scope_type' => 'church',
            'scope_key' => fake()->uuid(),
        ];
    }
}
