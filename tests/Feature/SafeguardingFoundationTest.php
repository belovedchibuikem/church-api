<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ChildProfile;
use App\Models\Person;
use App\Models\SafeguardingIncident;
use App\Models\User;
use App\Safeguarding\Actions\RegisterGuardianRelationshipAction;
use App\Safeguarding\Actions\ReportSafeguardingIncidentAction;
use App\Safeguarding\Contracts\RestrictedRecordAccessPolicy;
use App\Safeguarding\GuardianRelationshipStatus;
use App\Safeguarding\IncidentSeverity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SafeguardingFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guardian_registration_is_pending_idempotent_and_audited(): void
    {
        $guardian = Person::factory()->create();
        $child = Person::factory()->create();
        $actor = User::factory()->create();
        $action = $this->app->make(RegisterGuardianRelationshipAction::class);

        $first = $action->handle($guardian, $child, 'parent', $actor);
        $second = $action->handle($guardian, $child, 'parent', $actor);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(GuardianRelationshipStatus::Pending, $first->status);
        $this->assertSame(1, AuditEvent::query()->where('action', 'safeguarding.guardian_relationship.registered')->count());
    }

    public function test_incident_details_are_encrypted_hidden_and_not_copied_to_audit(): void
    {
        $summary = 'Restricted child welfare narrative.';
        $incident = $this->app->make(ReportSafeguardingIncidentAction::class)->handle(
            'welfare_concern',
            IncidentSeverity::High,
            $summary,
            Person::factory()->create(),
            User::factory()->create(),
        );

        $this->assertNotSame($summary, $incident->getRawOriginal('restricted_summary'));
        $this->assertArrayNotHasKey('restricted_summary', $incident->toArray());
        $this->assertSame($summary, $incident->refresh()->restricted_summary);
        $this->assertDatabaseMissing('audit_events', ['metadata' => $summary]);
        $this->assertSame(1, SafeguardingIncident::query()->count());
    }

    public function test_restricted_record_access_is_denied_until_governance_is_approved(): void
    {
        $decision = $this->app->make(RestrictedRecordAccessPolicy::class)->decide(
            User::factory()->make(),
            'safeguarding_incident',
            '01TEST',
            'view',
        );

        $this->assertFalse($decision->allowed);
        $this->assertSame('restricted_policy_pending', $decision->reasonCode);
    }

    public function test_child_date_of_birth_is_encrypted_and_hidden_by_default(): void
    {
        $dateOfBirth = '2014-03-12';
        $profile = ChildProfile::factory()->create(['date_of_birth' => $dateOfBirth]);

        $this->assertNotSame($dateOfBirth, $profile->getRawOriginal('date_of_birth'));
        $this->assertSame($dateOfBirth, $profile->refresh()->date_of_birth);
        $this->assertArrayNotHasKey('date_of_birth', $profile->toArray());
    }
}
