<?php

namespace Database\Factories;

use App\Models\PressPublication;
use App\Models\PressTranslation;
use App\Press\PressTranslationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PressTranslation>
 */
class PressTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'press_publication_id' => PressPublication::factory(),
            'target_language_code' => 'fr',
            'translated_title' => fake()->sentence(4),
            'translated_description' => fake()->paragraph(),
            'status' => PressTranslationStatus::MachineGenerated,
            'idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
            'request_fingerprint' => hash('sha256', Str::uuid()->toString()),
            'status_changed_at' => now(),
        ];
    }
}
