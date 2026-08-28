<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PressPublication;
use App\Models\PressPublicationContributor;
use App\Press\PressContributorRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressPublicationContributor>
 */
class PressPublicationContributorFactory extends Factory
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
            'person_id' => Person::factory(),
            'role' => PressContributorRole::Author,
        ];
    }
}
