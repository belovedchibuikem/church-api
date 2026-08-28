<?php

namespace Database\Factories;

use App\Models\KcaCohort;
use App\Models\KcaYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaCohort>
 */
class KcaCohortFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_year_id' => KcaYear::factory(),
            'code' => 'cohort-'.Str::lower(Str::random(10)),
            'name' => fake()->words(2, true).' Cohort',
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->endOfYear(),
        ];
    }
}
