<?php

namespace Database\Factories;

use App\Models\DataSubjectRequest;
use App\Models\Person;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DataSubjectRequest> */
class DataSubjectRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'request_type' => DataSubjectRequestType::Export,
            'status' => DataSubjectRequestStatus::PendingReview,
            'idempotency_key_hash' => hash('sha256', Str::random()),
            'requested_at' => now(),
        ];
    }
}
