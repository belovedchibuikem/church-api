<?php

namespace Database\Factories;

use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationKind;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationBroadcast>
 */
class CommunicationBroadcastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'communication_template_id' => CommunicationTemplate::factory(),
            'communication_audience_id' => CommunicationAudience::factory(),
            'kind' => CommunicationKind::Broadcast,
            'channel' => CommunicationChannel::Email,
            'purpose' => 'communications.ministry_updates',
            'status' => CommunicationBroadcastStatus::Draft,
            'scheduled_at' => null,
            'prepared_at' => null,
            'idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
            'created_by_user_id' => User::factory(),
        ];
    }
}
