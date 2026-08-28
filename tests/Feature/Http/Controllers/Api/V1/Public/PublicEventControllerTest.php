<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\Location;
use App\Models\MinistryEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicEventControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_paginated_published_upcoming_events_without_private_location_fields(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00 UTC'));
        $location = Location::factory()->create([
            'name' => 'Central Auditorium',
            'locality' => 'Lagos',
            'timezone' => 'Africa/Lagos',
            'address_line_one' => 'Private address detail',
            'postal_code' => '100001',
        ]);
        $ongoing = MinistryEvent::factory()->published()->for($location)->create([
            'name' => 'Ongoing Conference',
            'starts_at' => '2026-09-01 09:00:00',
            'ends_at' => '2026-09-01 11:00:00',
        ]);
        MinistryEvent::factory()->published()->create([
            'name' => 'Future Training',
            'starts_at' => '2026-09-02 09:00:00',
            'ends_at' => '2026-09-02 11:00:00',
        ]);
        MinistryEvent::factory()->create([
            'starts_at' => '2026-09-03 09:00:00',
            'ends_at' => '2026-09-03 11:00:00',
        ]);
        MinistryEvent::factory()->published()->create([
            'starts_at' => '2026-08-31 09:00:00',
            'ends_at' => '2026-08-31 11:00:00',
        ]);

        $response = $this->getJson('/api/v1/events?per_page=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ongoing->public_id)
            ->assertJsonPath('data.0.location.name', 'Central Auditorium')
            ->assertJsonPath('data.0.location.locality', 'Lagos')
            ->assertJsonPath('data.0.location.timezone', 'Africa/Lagos')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonMissingPath('data.0.location.address_line_one')
            ->assertJsonMissingPath('data.0.location.postal_code')
            ->assertJsonMissingPath('data.0.capacity')
            ->assertJsonMissingPath('data.0.registration_opens_at');
        $this->assertSame(
            ['id', 'category', 'name', 'starts_at', 'ends_at', 'location', 'fee'],
            array_keys($response->json('data.0')),
        );
    }

    public function test_applies_allowlisted_filters_and_sorts_deterministically(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00 UTC'));
        $later = MinistryEvent::factory()->published()->create([
            'category_code' => 'conference',
            'name' => 'Later Conference',
            'starts_at' => '2026-09-03 09:00:00',
            'ends_at' => '2026-09-03 11:00:00',
        ]);
        $earlier = MinistryEvent::factory()->published()->create([
            'category_code' => 'conference',
            'name' => 'Earlier Conference',
            'starts_at' => '2026-09-02 09:00:00',
            'ends_at' => '2026-09-02 11:00:00',
        ]);
        MinistryEvent::factory()->published()->create([
            'category_code' => 'training',
            'starts_at' => '2026-09-03 10:00:00',
            'ends_at' => '2026-09-03 12:00:00',
        ]);

        $response = $this->getJson(
            '/api/v1/events?category=conference&starts_from=2026-09-02&starts_until=2026-09-03&sort=-starts_at',
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $later->public_id)
            ->assertJsonPath('data.1.id', $earlier->public_id);
    }

    public function test_returns_422_for_non_allowlisted_sort_and_query_parameters(): void
    {
        $response = $this->getJson('/api/v1/events?sort=starts_at%3BDROP%20TABLE%20users&internal=true');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.sort.0', 'The selected sort is invalid.')
            ->assertJsonPath('error.details.fields.query.0', 'Unsupported query parameter: internal.');
    }

    public function test_returns_published_upcoming_event_detail_with_normalized_envelope(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00 UTC'));
        $event = MinistryEvent::factory()->published()->create([
            'name' => 'Public Conference',
            'fee_amount_minor' => 2500,
            'fee_currency' => 'NGN',
            'starts_at' => '2026-09-02 09:00:00',
            'ends_at' => '2026-09-02 11:00:00',
        ]);

        $response = $this->getJson("/api/v1/events/{$event->public_id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $event->public_id)
            ->assertJsonPath('data.name', 'Public Conference')
            ->assertJsonPath('data.fee.amount_minor', 2500)
            ->assertJsonPath('data.fee.currency', 'NGN')
            ->assertJsonPath('meta.api_version', 'v1')
            ->assertJsonStructure(['data', 'meta', 'correlation_id']);
    }

    public function test_returns_safe_404_for_unpublished_event_detail(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00 UTC'));
        $event = MinistryEvent::factory()->create([
            'starts_at' => '2026-09-02 09:00:00',
            'ends_at' => '2026-09-02 11:00:00',
        ]);

        $response = $this->getJson("/api/v1/events/{$event->public_id}");

        $response
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('error.message', 'The requested resource was not found.')
            ->assertJsonMissingPath('data');
    }

    public function test_returns_normalized_404_for_invalid_event_identifier(): void
    {
        $response = $this->getJson('/api/v1/events/not-an-ulid');

        $response
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonMissingPath('exception');
    }
}
