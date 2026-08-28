<?php

namespace Database\Factories;

use App\Communication\CommunicationChannel;
use App\Models\CommunicationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationTemplate>
 */
class CommunicationTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'communications.template.'.Str::lower(Str::random(10)),
            'channel' => CommunicationChannel::Email,
            'locale' => 'en',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
