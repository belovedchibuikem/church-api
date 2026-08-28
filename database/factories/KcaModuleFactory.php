<?php

namespace Database\Factories;

use App\Models\KcaModule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaModule>
 */
class KcaModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'module-'.Str::lower(Str::random(12)),
            'title' => fake()->sentence(3),
            'sequence' => fake()->numberBetween(1, 5000),
            'is_active' => true,
        ];
    }
}
