<?php

namespace Database\Factories;

use App\Administration\AdminWorkItemPriority;
use App\Administration\AdminWorkItemStatus;
use App\Models\AdminWorkItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminWorkItem>
 */
class AdminWorkItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'body' => fake()->optional()->paragraph(),
            'status' => AdminWorkItemStatus::Open,
            'priority' => AdminWorkItemPriority::Normal,
            'due_at' => null,
            'assigned_to_user_id' => null,
            'created_by_user_id' => User::factory(),
            'closed_at' => null,
        ];
    }
}
