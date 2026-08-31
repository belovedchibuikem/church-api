<?php

namespace Tests\Feature;

use App\Church\HomeChurchApplicationStatus;
use App\Church\HomeChurchStatus;
use App\Church\MeetingDay;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\Country;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\HomeChurchApplicationTransition;
use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use App\Support\Church\CreateHomeChurchApplicationAction;
use App\Support\Church\HomeChurchApplicationData;
use App\Support\Church\TransitionHomeChurchApplicationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class HomeChurchApplicationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_follows_the_approved_path_and_keeps_home_church_lifecycle_in_sync(): void
    {
        $actor = User::factory()->create();
        $application = $this->createHomeChurchApplication($actor);
        $creationAudit = AuditEvent::query()
            ->where('action', 'home_church.application.created')
            ->sole();
        $transition = $this->app->make(TransitionHomeChurchApplicationAction::class);
        Context::add('correlation_id', '2a30cf3f-ea8c-4c50-a797-11f6ee3abf10');

        $transition->handle($application, HomeChurchApplicationStatus::Submitted, 'applicant_submitted', $actor);
        $transition->handle($application, HomeChurchApplicationStatus::UnderReview, 'review_started', $actor);
        $transition->handle($application, HomeChurchApplicationStatus::InterviewOrientation, 'orientation_scheduled', $actor);
        $transition->handle($application, HomeChurchApplicationStatus::Approved, 'application_approved', $actor);
        $activeApplication = $transition->handle(
            $application,
            HomeChurchApplicationStatus::Active,
            'activation_completed',
            $actor,
        );
        $transitionCount = HomeChurchApplicationTransition::query()->count();
        $auditCount = AuditEvent::query()->count();
        $sameApplication = $transition->handle(
            $activeApplication,
            HomeChurchApplicationStatus::Active,
            'activation_retry',
            $actor,
        );

        $this->assertSame($activeApplication->getKey(), $sameApplication->getKey());
        $this->assertSame(HomeChurchApplicationStatus::Active, $sameApplication->status);
        $this->assertNotNull($sameApplication->home_church_id);
        $this->assertSame(1, HomeChurch::query()->count());
        $this->assertSame($transitionCount, HomeChurchApplicationTransition::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());

        $transition->handle($sameApplication, HomeChurchApplicationStatus::Suspended, 'safeguard_review', $actor, 'Safeguard review required.');
        $transition->handle($sameApplication, HomeChurchApplicationStatus::Active, 'suspension_resolved', $actor);
        $closedApplication = $transition->handle(
            $sameApplication,
            HomeChurchApplicationStatus::Closed,
            'ministry_closed',
            $actor,
            'Ministry closed after review.',
        );
        $homeChurch = HomeChurch::query()->findOrFail($closedApplication->home_church_id);

        $this->assertSame(HomeChurchApplicationStatus::Closed, $closedApplication->status);
        $this->assertNull($closedApplication->active_marker);
        $this->assertSame(HomeChurchStatus::Closed, $homeChurch->status);
        $this->assertSame($closedApplication->applicant_person_id, $homeChurch->leader_person_id);
        $this->assertSame($closedApplication->location_id, $homeChurch->location_id);
        $this->assertSame($closedApplication->administrative_unit_id, $homeChurch->administrative_unit_id);
        $this->assertSame('home_church', $homeChurch->scopeReference()->type);
        $this->assertSame($homeChurch->public_id, $homeChurch->scopeReference()->key);
        $this->assertSame(8, HomeChurchApplicationTransition::query()->count());
        $this->assertArrayNotHasKey('contact_email', $creationAudit->metadata);
        $this->assertArrayNotHasKey('contact_phone', $creationAudit->metadata);

        $firstTransition = HomeChurchApplicationTransition::query()->oldest('id')->firstOrFail();
        $this->assertSame($actor->getKey(), $firstTransition->actor_user_id);
        $this->assertSame('applicant_submitted', $firstTransition->reason_code);
        $this->assertSame('2a30cf3f-ea8c-4c50-a797-11f6ee3abf10', $firstTransition->correlation_id);
    }

    public function test_rejects_an_invalid_transition_without_writes_or_audit(): void
    {
        $actor = User::factory()->create();
        $application = $this->createHomeChurchApplication($actor);
        $auditCount = AuditEvent::query()->count();
        $wasRejected = false;

        try {
            $this->app->make(TransitionHomeChurchApplicationAction::class)->handle(
                $application,
                HomeChurchApplicationStatus::UnderReview,
                'review_started',
                $actor,
            );
            $this->fail('Expected the invalid transition to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(HomeChurchApplicationStatus::Draft, $application->fresh()->status);
        $this->assertSame(0, HomeChurchApplicationTransition::query()->count());
        $this->assertSame(0, HomeChurch::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_rejection_is_terminal_and_allows_a_later_application(): void
    {
        $actor = User::factory()->create();
        $application = $this->createHomeChurchApplication($actor);
        $transition = $this->app->make(TransitionHomeChurchApplicationAction::class);
        $transition->handle($application, HomeChurchApplicationStatus::Submitted, 'applicant_submitted', $actor);
        $transition->handle($application, HomeChurchApplicationStatus::UnderReview, 'review_started', $actor);
        $rejected = $transition->handle(
            $application,
            HomeChurchApplicationStatus::Rejected,
            'requirements_not_met',
            $actor,
            'Requirements were not met.',
        );
        $terminalTransitionRejected = false;

        try {
            $transition->handle(
                $rejected,
                HomeChurchApplicationStatus::Submitted,
                'retry_rejected_application',
                $actor,
            );
            $this->fail('Expected the terminal application to reject another transition.');
        } catch (InvalidArgumentException) {
            $terminalTransitionRejected = true;
        }

        $newApplication = $this->createHomeChurchApplication($actor, $rejected->applicant()->firstOrFail());

        $this->assertTrue($terminalTransitionRejected);
        $this->assertSame(HomeChurchApplicationStatus::Rejected, $rejected->status);
        $this->assertNull($rejected->active_marker);
        $this->assertSame(HomeChurchApplicationStatus::Draft, $newApplication->status);
        $this->assertSame(2, HomeChurchApplication::query()->count());
    }

    public function test_rejects_duplicate_open_applications(): void
    {
        $actor = User::factory()->create();
        $application = $this->createHomeChurchApplication($actor);
        $auditCount = AuditEvent::query()->count();
        $wasRejected = false;

        try {
            $this->createHomeChurchApplication($actor, $application->applicant()->firstOrFail());
            $this->fail('Expected duplicate open application to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(1, HomeChurchApplication::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_rejects_an_application_with_a_mismatched_location_unit(): void
    {
        $actor = User::factory()->create();
        $church = Church::factory()->create();
        $applicant = Person::factory()->create();
        $otherUnit = AdministrativeUnit::factory()->create();
        $location = Location::query()->findOrFail($church->location_id);
        $wasRejected = false;

        try {
            $this->app->make(CreateHomeChurchApplicationAction::class)->handle(
                $this->applicationData($applicant, $church, $location, $otherUnit),
                $actor,
            );
            $this->fail('Expected the mismatched application location to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, HomeChurchApplication::query()->count());
    }

    public function test_rejects_an_application_outside_the_sponsoring_church_country(): void
    {
        $actor = User::factory()->create();
        $applicant = Person::factory()->create();
        $churchCountry = Country::factory()->create(['iso_code' => 'GH']);
        $churchLevel = AdministrativeLevel::factory()->create(['country_id' => $churchCountry->getKey()]);
        $churchUnit = AdministrativeUnit::factory()->create([
            'country_id' => $churchCountry->getKey(),
            'administrative_level_id' => $churchLevel->getKey(),
        ]);
        $churchLocation = Location::factory()->create([
            'country_id' => $churchCountry->getKey(),
            'administrative_unit_id' => $churchUnit->getKey(),
        ]);
        $church = Church::factory()->create([
            'location_id' => $churchLocation->getKey(),
            'administrative_unit_id' => $churchUnit->getKey(),
        ]);
        $foreignCountry = Country::factory()->create(['iso_code' => 'UG']);
        $foreignLevel = AdministrativeLevel::factory()->create(['country_id' => $foreignCountry->getKey()]);
        $foreignUnit = AdministrativeUnit::factory()->create([
            'country_id' => $foreignCountry->getKey(),
            'administrative_level_id' => $foreignLevel->getKey(),
        ]);
        $foreignLocation = Location::factory()->create([
            'country_id' => $foreignCountry->getKey(),
            'administrative_unit_id' => $foreignUnit->getKey(),
        ]);
        $wasRejected = false;

        try {
            $this->app->make(CreateHomeChurchApplicationAction::class)->handle(
                $this->applicationData($applicant, $church, $foreignLocation, $foreignUnit),
                $actor,
            );
            $this->fail('Expected the cross-country application to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, HomeChurchApplication::query()->count());
    }

    public function test_workflow_state_and_activation_link_are_not_mass_assignable(): void
    {
        $application = HomeChurchApplication::factory()->create();

        $application->fill([
            'status' => HomeChurchApplicationStatus::Active->value,
            'active_marker' => null,
            'home_church_id' => 123,
        ]);

        $this->assertSame(HomeChurchApplicationStatus::Draft, $application->status);
        $this->assertSame(1, $application->active_marker);
        $this->assertNull($application->home_church_id);
    }

    public function test_request_information_and_deferral_require_notes_and_honour_expected_status(): void
    {
        $actor = User::factory()->create();
        $application = $this->createHomeChurchApplication($actor);
        $transition = $this->app->make(TransitionHomeChurchApplicationAction::class);
        $transition->handle($application, HomeChurchApplicationStatus::Submitted, 'applicant_submitted', $actor);
        $reviewed = $transition->handle($application, HomeChurchApplicationStatus::UnderReview, 'review_started', $actor);

        $notesRequired = false;
        try {
            $transition->handle($reviewed, HomeChurchApplicationStatus::InformationRequired, 'missing_docs', $actor);
            $this->fail('Expected notes to be required.');
        } catch (InvalidArgumentException) {
            $notesRequired = true;
        }

        $stale = false;
        try {
            $transition->handle(
                $reviewed,
                HomeChurchApplicationStatus::InformationRequired,
                'missing_docs',
                $actor,
                'Please upload venue evidence.',
                HomeChurchApplicationStatus::Submitted,
            );
            $this->fail('Expected a stale expected_status to be rejected.');
        } catch (InvalidArgumentException) {
            $stale = true;
        }

        $waiting = $transition->handle(
            $reviewed,
            HomeChurchApplicationStatus::InformationRequired,
            'missing_docs',
            $actor,
            'Please upload venue evidence.',
            HomeChurchApplicationStatus::UnderReview,
        );
        $resumed = $transition->handle($waiting, HomeChurchApplicationStatus::UnderReview, 'info_received', $actor);
        $deferred = $transition->handle(
            $resumed,
            HomeChurchApplicationStatus::Deferred,
            'capacity',
            $actor,
            'Defer until leadership training is complete.',
        );

        $this->assertTrue($notesRequired);
        $this->assertTrue($stale);
        $this->assertSame(HomeChurchApplicationStatus::Deferred, $deferred->status);
        $this->assertSame(5, HomeChurchApplicationTransition::query()->count());
        if (Schema::hasColumn('home_church_application_transitions', 'notes')) {
            $this->assertNotNull(
                HomeChurchApplicationTransition::query()->where('to_status', HomeChurchApplicationStatus::InformationRequired)->value('notes'),
            );
        }
    }

    public function test_contact_fields_are_encrypted_and_hidden_by_default(): void
    {
        $application = $this->createHomeChurchApplication();
        $stored = DB::table('home_church_applications')->where('id', $application->getKey())->firstOrFail();

        $this->assertNotSame('leader@example.test', $stored->contact_email);
        $this->assertNotSame('+233 20 123 4567', $stored->contact_phone);
        $this->assertSame('leader@example.test', $application->refresh()->contact_email);
        $this->assertSame('+233 20 123 4567', $application->contact_phone);
        $this->assertArrayNotHasKey('contact_email', $application->toArray());
        $this->assertArrayNotHasKey('contact_phone', $application->toArray());
    }

    private function createHomeChurchApplication(?User $actor = null, ?Person $applicant = null): HomeChurchApplication
    {
        $church = Church::query()->first() ?? Church::factory()->create();
        $location = Location::query()->findOrFail($church->location_id);
        $unit = AdministrativeUnit::query()->findOrFail($church->administrative_unit_id);

        return $this->app->make(CreateHomeChurchApplicationAction::class)->handle(
            $this->applicationData($applicant ?? Person::factory()->create(), $church, $location, $unit),
            $actor,
        );
    }

    private function applicationData(
        Person $applicant,
        Church $church,
        Location $location,
        AdministrativeUnit $unit,
    ): HomeChurchApplicationData {
        return new HomeChurchApplicationData(
            applicant: $applicant,
            church: $church,
            location: $location,
            administrativeUnit: $unit,
            proposedName: 'Grace Street Home Church',
            expectedParticipants: 12,
            meetingDay: MeetingDay::Saturday,
            meetingTime: '17:30',
            contactEmail: 'leader@example.test',
            contactPhone: '+233 20 123 4567',
            guidelinesAgreedAt: now(),
        );
    }
}
