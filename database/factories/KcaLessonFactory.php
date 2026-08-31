<?php

namespace Database\Factories;

use App\Models\KcaLesson;
use App\Models\KcaModule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaLesson>
 */
class KcaLessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_module_id' => KcaModule::factory(),
            'code' => 'lesson-'.Str::lower(Str::random(12)),
            'title' => fake()->sentence(4),
            'sequence' => fake()->numberBetween(1, 5000),
            'day_index' => 1,
            'lesson_type' => 'text',
            'requires_acknowledgement' => true,
            'summary' => 'Published lesson summary.',
            'body' => 'Read this lesson, then acknowledge completion.',
        ];
    }
}
