<?php

namespace Database\Factories;

use App\Kca\KcaPrerequisiteRequirement;
use App\Models\KcaModule;
use App\Models\KcaModulePrerequisite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaModulePrerequisite>
 */
class KcaModulePrerequisiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_module_id' => KcaModule::factory(),
            'prerequisite_module_id' => KcaModule::factory(),
            'requirement' => KcaPrerequisiteRequirement::PreviousModuleComplete,
        ];
    }
}
