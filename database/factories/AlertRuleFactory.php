<?php

namespace Database\Factories;

use App\Models\AlertRule;
use App\Models\User;
use App\Reporting\AlertSeverity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'alerts.'.Str::lower(Str::random(12)),
            'title' => fake()->sentence(4),
            'condition_type' => 'condition.'.Str::lower(Str::random(10)),
            'severity' => AlertSeverity::Warning,
            'scope_type' => null,
            'scope_key' => null,
            'configuration' => [],
            'is_active' => false,
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => true]);
    }
}
