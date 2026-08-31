<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PressPublication;
use App\Models\PressPublicationReview;
use App\Press\PressReviewDecision;
use App\Press\PressReviewStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressPublicationReview>
 */
class PressPublicationReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'press_publication_id' => PressPublication::factory(),
            'reviewer_person_id' => Person::factory(),
            'stage' => PressReviewStage::Editorial,
            'decision' => PressReviewDecision::Approved,
            'comments' => fake()->sentence(),
            'decided_at' => now(),
        ];
    }
}
