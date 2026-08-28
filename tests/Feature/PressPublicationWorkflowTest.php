<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Person;
use App\Models\PressPublication;
use App\Models\PressPublicationContributor;
use App\Models\PressPublicationTransition;
use App\Models\User;
use App\Press\Isbn;
use App\Press\PressContributorRole;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationData;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use App\Support\Press\AddPressPublicationContributorAction;
use App\Support\Press\AssignPressPublicationIsbnAction;
use App\Support\Press\CreatePressPublicationAction;
use App\Support\Press\TransitionPressPublicationAction;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use Tests\TestCase;

class PressPublicationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_follows_the_approved_workflow_with_append_only_transition_evidence(): void
    {
        $actor = User::factory()->create();
        $publication = $this->createPublication($actor);
        $transition = $this->app->make(TransitionPressPublicationAction::class);
        Context::add('correlation_id', '4e7ee60f-66ef-46c8-8f8f-f5007874e07b');

        foreach ([
            PressPublicationStatus::EditorialReview,
            PressPublicationStatus::TheologicalReview,
            PressPublicationStatus::CopyEditing,
            PressPublicationStatus::Design,
        ] as $status) {
            $publication = $transition->handle($publication, $status, $actor, 'workflow.progressed');
        }

        $publication = $this->app->make(AssignPressPublicationIsbnAction::class)->handle(
            $publication,
            '978-0-306-40615-7',
            $actor,
            'isbn.assigned',
        );
        $publication = $transition->handle($publication, PressPublicationStatus::PublicationApproval, $actor, 'publication.reviewed');
        $publication = $transition->handle($publication, PressPublicationStatus::Published, $actor, 'publication.approved');
        $publication = $transition->handle($publication, PressPublicationStatus::Distribution, $actor, 'distribution.started');
        $transitionCount = PressPublicationTransition::query()->count();
        $auditCount = AuditEvent::query()->count();
        $retry = $transition->handle($publication, PressPublicationStatus::Distribution, $actor, 'distribution.retry');

        $this->assertSame(PressPublicationStatus::Distribution, $retry->status);
        $this->assertSame(PressPublicationAvailability::Available, $retry->availability);
        $this->assertSame('9780306406157', $retry->isbn);
        $this->assertNotNull($retry->published_at);
        $this->assertNotNull($retry->distributed_at);
        $this->assertSame(8, $transitionCount);
        $this->assertSame($transitionCount, PressPublicationTransition::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
        $this->assertSame(
            '4e7ee60f-66ef-46c8-8f8f-f5007874e07b',
            PressPublicationTransition::query()->oldest('id')->value('correlation_id'),
        );
    }

    public function test_rejects_skipped_transitions_without_writes(): void
    {
        $actor = User::factory()->create();
        $publication = $this->createPublication($actor);
        $auditCount = AuditEvent::query()->count();

        try {
            $this->app->make(TransitionPressPublicationAction::class)->handle(
                $publication,
                PressPublicationStatus::Published,
                $actor,
                'publication.approved',
            );
            $this->fail('Expected the skipped transition to be rejected.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $this->assertSame(PressPublicationStatus::Manuscript, $publication->fresh()->status);
        $this->assertSame(0, PressPublicationTransition::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_invalid_isbn_checksum_is_denied(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Isbn::from('9780306406158');
    }

    public function test_creation_is_idempotent_and_conflicting_retry_is_denied(): void
    {
        $actor = User::factory()->create();
        $action = $this->app->make(CreatePressPublicationAction::class);
        $data = $this->publicationData();
        $idempotencyKey = 'publication-request-0001';
        $publication = $action->handle($data, $actor, $idempotencyKey);
        $retry = $action->handle($data, $actor, $idempotencyKey);

        $this->assertSame($publication->getKey(), $retry->getKey());
        $this->assertSame(1, PressPublication::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'press.publication.created')->count());

        $this->expectException(DomainException::class);
        $action->handle($this->publicationData('Different title'), $actor, $idempotencyKey);
    }

    public function test_contributors_reference_canonical_people_and_are_idempotent(): void
    {
        $actor = User::factory()->create();
        $person = Person::factory()->create();
        $publication = $this->createPublication($actor);
        $action = $this->app->make(AddPressPublicationContributorAction::class);
        $contributor = $action->handle($publication, $person, PressContributorRole::Author, $actor);
        $retry = $action->handle($publication, $person, PressContributorRole::Author, $actor);

        $this->assertSame($contributor->getKey(), $retry->getKey());
        $this->assertSame($person->getKey(), $contributor->person_id);
        $this->assertSame(1, PressPublicationContributor::query()->count());
        $audit = AuditEvent::query()->where('action', 'press.publication.contributor_added')->sole();
        $this->assertArrayNotHasKey('person_id', $audit->metadata);
    }

    public function test_workflow_fields_and_idempotency_evidence_are_not_mass_assignable(): void
    {
        $publication = PressPublication::factory()->create();

        $publication->fill([
            'status' => PressPublicationStatus::Distribution->value,
            'availability' => PressPublicationAvailability::Available->value,
            'isbn' => '9780306406157',
            'idempotency_key_hash' => str_repeat('a', 64),
        ]);

        $this->assertSame(PressPublicationStatus::Manuscript, $publication->status);
        $this->assertSame(PressPublicationAvailability::Unavailable, $publication->availability);
        $this->assertNull($publication->isbn);
        $this->assertNotSame(str_repeat('a', 64), $publication->idempotency_key_hash);
    }

    private function createPublication(User $actor): PressPublication
    {
        return $this->app->make(CreatePressPublicationAction::class)->handle(
            $this->publicationData(),
            $actor,
            'publication-request-'.fake()->uuid(),
        );
    }

    private function publicationData(string $title = 'A faithful publication'): PressPublicationData
    {
        return new PressPublicationData(
            title: $title,
            publisherName: 'Family House Press',
            languageCode: 'en-GB',
            format: PressPublicationFormat::Pdf,
            subtitle: 'A subtitle that stays out of audit metadata',
            publicationDate: '2026-08-26',
            copyrightYear: 2026,
            pageCount: 180,
            category: 'discipleship',
            description: 'Pre-publication manuscript metadata is not audit payload.',
            priceMinor: 2500,
            currencyCode: 'ngn',
        );
    }
}
