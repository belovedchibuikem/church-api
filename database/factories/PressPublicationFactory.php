<?php

namespace Database\Factories;

use App\Models\PressPublication;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PressPublication>
 */
class PressPublicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'publisher_name' => fake()->company(),
            'language_code' => 'en',
            'category' => fake()->word(),
            'format' => PressPublicationFormat::Print,
            'availability' => PressPublicationAvailability::Unavailable,
            'status' => PressPublicationStatus::Manuscript,
            'idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
            'request_fingerprint' => hash('sha256', Str::uuid()->toString()),
            'status_changed_at' => now(),
        ];
    }
}
