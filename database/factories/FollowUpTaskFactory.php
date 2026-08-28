<?php

namespace Database\Factories;

use App\Church\FollowUpTaskStatus;
use App\Church\FollowUpTaskType;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUpTask>
 */
class FollowUpTaskFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (FollowUpTask $task): void {
            $task->status = FollowUpTaskStatus::Pending;
            $task->completed_at = null;
            $task->completion_reason_code = null;
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_timer_id' => FirstTimer::factory(),
            'assigned_to_person_id' => null,
            'type' => FollowUpTaskType::FirstTimerContact,
            'due_at' => fake()->dateTimeBetween('+1 hour', '+7 days'),
        ];
    }
}
