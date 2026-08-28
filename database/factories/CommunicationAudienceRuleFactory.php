<?php

namespace Database\Factories;

use App\Communication\CommunicationAudienceRuleType;
use App\Models\CommunicationAudience;
use App\Models\CommunicationAudienceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationAudienceRule>
 */
class CommunicationAudienceRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'communication_audience_id' => CommunicationAudience::factory(),
            'type' => CommunicationAudienceRuleType::AllUsers,
            'selector_key' => null,
            'scope_type' => null,
            'scope_key' => null,
        ];
    }
}
