<?php

namespace Database\Factories;

use App\Kca\KcaAssignmentState;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaEvidenceReview>
 */
class KcaEvidenceReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_evidence_submission_id' => KcaEvidenceSubmission::factory(),
            'reviewer_person_id' => Person::factory(),
            'reviewed_by_user_id' => User::factory(),
            'outcome' => KcaAssignmentState::Approved,
            'reviewed_at' => now(),
        ];
    }
}
