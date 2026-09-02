<?php

namespace Database\Factories;

use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KcaAdmissionLetter> */
class KcaAdmissionLetterFactory extends Factory
{
    protected $model = KcaAdmissionLetter::class;

    public function definition(): array
    {
        return [
            'kca_application_id' => KcaApplication::factory(),
            'reference_code' => 'KCA/ADM/'.now()->year.'/'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'batch_label' => 'Batch '.now()->year,
            'letter_body' => null,
            'signer_name' => 'Provost KCA',
            'signer_title' => 'Provost, Kingdom Citizens Academy',
            'issued_by_user_id' => User::factory(),
            'issued_at' => now()->utc(),
        ];
    }
}
