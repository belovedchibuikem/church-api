<?php

namespace Tests\Feature;

use App\Church\ChurchMembershipStatus;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Country;
use App\Models\HomeChurch;
use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use App\Support\Church\CreateChurchAction;
use App\Support\Church\EndChurchMembershipAction;
use App\Support\Church\StartChurchMembershipAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ChurchFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_a_church_with_location_scope_and_audit_evidence(): void
    {
        [$location, $unit] = $this->createLocationAndUnit('GH');
        $actor = User::factory()->create();

        $church = $this->app->make(CreateChurchAction::class)
            ->handle('  Family House Accra  ', $location, $unit, $actor);

        $this->assertTrue(Str::isUlid($church->public_id));
        $this->assertSame('Family House Accra', $church->name);
        $this->assertSame($location->getKey(), $church->location_id);
        $this->assertSame($unit->getKey(), $church->administrative_unit_id);
        $this->assertSame('church', $church->scopeReference()->type);
        $this->assertSame($church->public_id, $church->scopeReference()->key);

        $audit = AuditEvent::query()->sole();
        $this->assertSame('church.created', $audit->action);
        $this->assertSame('church', $audit->scope_type);
        $this->assertSame($church->public_id, $audit->scope_id);
        $this->assertSame($actor->getKey(), $audit->actor_user_id);
    }

    public function test_rejects_a_church_when_the_location_and_unit_do_not_match(): void
    {
        [$location] = $this->createLocationAndUnit('GH');
        [, $otherUnit] = $this->createLocationAndUnit('UG');
        $wasRejected = false;

        try {
            $this->app->make(CreateChurchAction::class)
                ->handle('Invalid Cross Location Church', $location, $otherUnit);
            $this->fail('Expected the mismatched location to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, Church::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_membership_reuses_the_canonical_person_and_preserves_lifecycle_history(): void
    {
        $church = Church::factory()->create();
        $person = Person::factory()->create();
        $actor = User::factory()->create();
        $start = $this->app->make(StartChurchMembershipAction::class);
        $firstMembership = $start->handle($person, $church, actor: $actor);
        $duplicateRejected = false;

        try {
            $start->handle($person, $church, actor: $actor);
            $this->fail('Expected duplicate active membership to be rejected.');
        } catch (InvalidArgumentException) {
            $duplicateRejected = true;
        }

        $endedMembership = $this->app->make(EndChurchMembershipAction::class)
            ->handle($firstMembership, 'member_transferred', $actor);
        $secondMembership = $start->handle($person, $church, actor: $actor);

        $this->assertTrue($duplicateRejected);
        $this->assertSame($person->getKey(), $firstMembership->person_id);
        $this->assertSame(ChurchMembershipStatus::Ended, $endedMembership->status);
        $this->assertNull($endedMembership->active_marker);
        $this->assertSame('member_transferred', $endedMembership->end_reason_code);
        $this->assertSame(ChurchMembershipStatus::Active, $secondMembership->status);
        $this->assertSame($person->getKey(), $secondMembership->person_id);
        $this->assertSame(1, Person::query()->count());
        $this->assertSame(2, ChurchMembership::query()->count());
    }

    public function test_rejects_membership_in_a_home_church_owned_by_another_church(): void
    {
        $church = Church::factory()->create();
        $otherChurch = Church::factory()->create();
        $foreignHomeChurch = HomeChurch::factory()->for($otherChurch)->create();
        $person = Person::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(StartChurchMembershipAction::class)
                ->handle($person, $church, $foreignHomeChurch);
            $this->fail('Expected cross-church membership to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, ChurchMembership::query()->count());
    }

    /** @return array{Location, AdministrativeUnit} */
    private function createLocationAndUnit(string $isoCode): array
    {
        $country = Country::factory()->create(['iso_code' => $isoCode]);
        $level = AdministrativeLevel::factory()->create(['country_id' => $country->getKey()]);
        $unit = AdministrativeUnit::factory()->create([
            'country_id' => $country->getKey(),
            'administrative_level_id' => $level->getKey(),
        ]);
        $location = Location::factory()->create([
            'country_id' => $country->getKey(),
            'administrative_unit_id' => $unit->getKey(),
        ]);

        return [$location, $unit];
    }
}
