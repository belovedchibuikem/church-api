<?php

namespace Database\Factories;

use App\Models\KcaYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaYear>
 */
class KcaYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'year-'.Str::lower(Str::random(10)),
            'name' => fake()->year().' KCA Year',
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->endOfYear(),
        ];
    }
}
