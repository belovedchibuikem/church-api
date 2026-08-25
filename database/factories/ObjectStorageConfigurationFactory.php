<?php

namespace Database\Factories;

use App\Models\ObjectStorageConfiguration;
use App\Storage\ObjectStorageDriver;
use App\Storage\ObjectStorageValidationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjectStorageConfiguration>
 */
class ObjectStorageConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver' => ObjectStorageDriver::S3,
            'access_key_id' => 'test-access-key-'.fake()->unique()->uuid(),
            'secret_access_key' => fake()->sha256(),
            'region' => 'us-east-1',
            'bucket' => fake()->unique()->slug(3),
            'endpoint' => null,
            'url' => null,
            'root_prefix' => null,
            'use_path_style_endpoint' => false,
            'is_active' => false,
            'configuration_revision' => 1,
            'last_validation_status' => null,
            'last_validation_failure_code' => null,
            'last_validation_attempted_at' => null,
            'validated_at' => null,
            'activated_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
            'last_validation_status' => ObjectStorageValidationStatus::Succeeded,
            'last_validation_attempted_at' => now(),
            'validated_at' => now(),
            'activated_at' => now(),
        ]);
    }
}
