<?php

namespace Database\Factories;

use App\Models\KcaCertificate;
use App\Models\KcaCertificateRevocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaCertificateRevocation>
 */
class KcaCertificateRevocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kca_certificate_id' => KcaCertificate::factory(),
            'reason_code' => 'administrative_correction',
            'notes' => null,
            'revoked_by_user_id' => User::factory(),
            'revoked_at' => now(),
        ];
    }
}
