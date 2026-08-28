<?php

namespace Database\Factories;

use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\User;
use App\Reporting\AlertOccurrenceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AlertOccurrence>
 */
class AlertOccurrenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_rule_id' => AlertRule::factory()->active(),
            'condition_reference_type' => 'record',
            'condition_reference_key' => Str::ulid()->toBase32(),
            'condition_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
            'scope_type' => null,
            'scope_key' => null,
            'status' => AlertOccurrenceStatus::Open,
            'active_marker' => 1,
            'summary' => fake()->sentence(),
            'opened_at' => now(),
            'acknowledged_by_user_id' => null,
            'acknowledged_at' => null,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
            'resolution_reason_code' => null,
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AlertOccurrenceStatus::Acknowledged,
            'acknowledged_by_user_id' => User::factory(),
            'acknowledged_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AlertOccurrenceStatus::Resolved,
            'active_marker' => null,
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
            'resolution_reason_code' => 'condition_cleared',
        ]);
    }
}
