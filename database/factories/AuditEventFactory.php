<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => null,
            'action' => 'testing.audit.recorded',
            'target_type' => null,
            'target_id' => null,
            'scope_type' => null,
            'scope_id' => null,
            'correlation_id' => fake()->uuid(),
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
