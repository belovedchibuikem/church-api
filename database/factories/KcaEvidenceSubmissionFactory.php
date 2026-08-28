<?php

namespace Database\Factories;

use App\Files\FileAssetClassification;
use App\Kca\KcaAssignmentState;
use App\Models\FileAsset;
use App\Models\KcaAssignment;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceSubmission;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaEvidenceSubmission>
 */
class KcaEvidenceSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_assignment_id' => KcaAssignment::factory()->inState(KcaAssignmentState::Submitted),
            'kca_enrollment_id' => fn (array $attributes): int => KcaAssignment::query()
                ->findOrFail($attributes['kca_assignment_id'])
                ->kca_enrollment_id,
            'file_asset_id' => function (array $attributes): int {
                $enrollment = KcaEnrollment::query()->findOrFail($attributes['kca_enrollment_id']);

                return FileAsset::factory()
                    ->available()
                    ->for(Person::query()->findOrFail($enrollment->person_id), 'owner')
                    ->create([
                        'purpose' => 'kca.evidence',
                        'classification' => FileAssetClassification::Confidential,
                    ])
                    ->getKey();
            },
            'submitted_by_person_id' => fn (array $attributes): int => KcaEnrollment::query()
                ->findOrFail($attributes['kca_enrollment_id'])
                ->person_id,
            'idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
            'submitted_at' => now(),
        ];
    }
}
