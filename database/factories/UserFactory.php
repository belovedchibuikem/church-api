<?php

namespace Database\Factories;

use App\Identity\UserAccountStatus;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withPerson(): static
    {
        return $this->for(Person::factory()->withProfile(), 'person');
    }

    public function suspended(string $reason = 'policy.violation'): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_status' => UserAccountStatus::Suspended->value,
            'suspension_reason' => $reason,
            'suspended_at' => now(),
            'reactivated_at' => null,
        ]);
    }
}
