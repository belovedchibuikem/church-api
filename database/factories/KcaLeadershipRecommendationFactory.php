<?php

namespace Database\Factories;

use App\Models\KcaApplication;
use App\Models\KcaLeadershipRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaLeadershipRecommendation>
 */
class KcaLeadershipRecommendationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kca_application_id' => KcaApplication::factory(),
            'recommender_name' => fake()->name(),
            'recommender_email' => fake()->safeEmail(),
            'recommender_role' => 'Pastor',
            'token_hash' => hash('sha256', (string) Str::ulid()),
            'status' => 'requested',
        ];
    }
}
