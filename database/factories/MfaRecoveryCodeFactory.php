<?php

namespace Database\Factories;

use App\Models\MfaMethod;
use App\Models\MfaRecoveryCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<MfaRecoveryCode>
 */
class MfaRecoveryCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mfa_method_id' => MfaMethod::factory(),
            'code_hash' => Hash::make(Str::random(20)),
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'used_at' => now(),
        ]);
    }
}
