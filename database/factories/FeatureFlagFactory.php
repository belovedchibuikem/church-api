<?php

namespace Database\Factories;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Support\Platform\PlatformContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureFlag>
 */
class FeatureFlagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $context = new PlatformContext(PlatformContext::AllEnvironments);

        return [
            'key' => 'platform.testing.feature',
            'environment' => $context->environment,
            'scope_type' => null,
            'scope_key' => null,
            'context_hash' => $context->hash(),
            'is_enabled' => false,
            'rollout_percentage' => 100,
            'starts_at' => null,
            'ends_at' => null,
            'updated_by_user_id' => User::factory(),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => true,
        ]);
    }
}
