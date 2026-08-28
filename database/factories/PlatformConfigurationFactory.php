<?php

namespace Database\Factories;

use App\Models\PlatformConfiguration;
use App\Models\User;
use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use App\Support\Platform\PlatformContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformConfiguration>
 */
class PlatformConfigurationFactory extends Factory
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
            'key' => 'platform.testing.value',
            'value_type' => ConfigurationValueType::String,
            'classification' => ConfigurationClassification::Internal,
            'environment' => $context->environment,
            'scope_type' => null,
            'scope_key' => null,
            'context_hash' => $context->hash(),
            'stored_value' => 'test-value',
            'updated_by_user_id' => User::factory(),
        ];
    }
}
