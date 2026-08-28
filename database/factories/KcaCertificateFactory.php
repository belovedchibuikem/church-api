<?php

namespace Database\Factories;

use App\Models\KcaCertificate;
use App\Models\KcaEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaCertificate>
 */
class KcaCertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_enrollment_id' => KcaEnrollment::factory(),
            'person_id' => fn (array $attributes): int => KcaEnrollment::query()
                ->findOrFail($attributes['kca_enrollment_id'])
                ->person_id,
            'certificate_number' => 'KCA-CERT-'.Str::upper(Str::random(12)),
            'completion_on' => now()->toDateString(),
            'issued_at' => now(),
            'digital_signature_reference' => null,
            'verification_code_hash' => hash('sha256', Str::ulid()->toString()),
            'issuance_key_hash' => hash('sha256', Str::uuid()->toString()),
            'issued_by_user_id' => User::factory(),
        ];
    }
}
