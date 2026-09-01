<?php

namespace Database\Factories;

use App\Models\KcaChapter;
use App\Models\KcaLesson;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaChapter>
 */
class KcaChapterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_lesson_id' => KcaLesson::factory(),
            'code' => 'ch-'.Str::lower(Str::random(8)),
            'title' => fake()->sentence(3),
            'sequence' => fake()->numberBetween(1, 5000),
            'summary' => 'Chapter summary.',
            'body' => 'Read this chapter, then continue.',
        ];
    }
}
