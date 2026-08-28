<?php

namespace Database\Factories;

use App\Models\SafeguardingIncident;
use App\Safeguarding\IncidentSeverity;
use App\Safeguarding\IncidentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SafeguardingIncident> */
class SafeguardingIncidentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference_code' => 'SG-'.Str::upper(Str::random(16)),
            'concern_type' => 'welfare_concern',
            'severity' => IncidentSeverity::Medium,
            'status' => IncidentStatus::Reported,
            'restricted_summary' => fake()->sentence(),
            'reported_at' => now(),
        ];
    }
}
