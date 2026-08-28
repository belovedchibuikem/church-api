<?php

namespace Database\Factories;

use App\Events\EventRegistrationStatus;
use App\Models\EventRegistration;
use App\Models\MinistryEvent;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventRegistration>
 */
class EventRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ministry_event_id' => MinistryEvent::factory(), 'person_id' => Person::factory(),
            'status' => EventRegistrationStatus::Confirmed, 'idempotency_scope_hash' => hash('sha256', Str::uuid()->toString()),
            'registered_at' => now(), 'confirmed_at' => now(),
        ];
    }
}
