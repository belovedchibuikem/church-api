<?php

namespace Database\Factories;

use App\Models\KcaGovernanceConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaGovernanceConfiguration>
 */
class KcaGovernanceConfigurationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pass_threshold_percent' => 70,
            'attendance_threshold_percent' => 75,
            'require_final_assessment' => true,
            'require_signed_pdf' => false,
            'certificate_signer_name' => 'Academic Registrar',
            'certificate_signer_title' => 'Registrar, Kingdom Change Agents',
            'is_active' => true,
            'configuration_revision' => 1,
        ];
    }
}
