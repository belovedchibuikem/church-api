<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PressPublication;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationData;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use App\Press\PressPublicationVisibility;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use App\Support\Press\ApplyPressPublicationSchedulesAction;
use App\Support\Press\CreatePressPublicationAction;
use App\Support\Press\TransitionPressPublicationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PressPublicationTypesApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sermon_can_publish_without_isbn_and_is_listed_publicly(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions([
            'press.publications.manage',
            'press.publications.transition',
            'press.publications.view',
        ], $scope);
        $this->authenticate($actor);

        $created = $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'press-sermon-create-0001'])
            ->postJson('/api/v1/admin/press/publications', [
                'title' => 'Hope Sunday Message',
                'publisher_name' => 'Family House Press',
                'language_code' => 'en',
                'format' => 'audio',
                'publication_type' => 'sermon',
                'type_metadata' => [
                    'speaker' => 'Pastor Demo',
                    'preached_date' => '2026-08-30',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.publication_type', 'sermon')
            ->assertJsonPath('data.status', 'manuscript');

        $id = $created->json('data.id');
        $publication = PressPublication::query()->where('public_id', $id)->firstOrFail();
        $transition = $this->app->make(TransitionPressPublicationAction::class);

        foreach ([
            PressPublicationStatus::EditorialReview,
            PressPublicationStatus::TheologicalReview,
            PressPublicationStatus::CopyEditing,
            PressPublicationStatus::Design,
            PressPublicationStatus::PublicationApproval,
            PressPublicationStatus::Published,
            PressPublicationStatus::Distribution,
        ] as $status) {
            $publication = $transition->handle($publication, $status, $actor, 'workflow.progressed');
        }

        $this->assertNull($publication->isbn);
        $this->assertSame(PressPublicationStatus::Distribution, $publication->status);

        $this->getJson('/api/v1/press/publications?filter[publication_type]=sermon')
            ->assertOk()
            ->assertJsonPath('data.0.id', $publication->public_id)
            ->assertJsonPath('data.0.publication_type', 'sermon');
    }

    public function test_sermon_create_without_speaker_is_rejected(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['press.publications.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'press-sermon-invalid-0001'])
            ->postJson('/api/v1/admin/press/publications', [
                'title' => 'Unattributed Sermon',
                'publisher_name' => 'Family House Press',
                'language_code' => 'en',
                'format' => 'audio',
                'publication_type' => 'sermon',
            ])
            ->assertStatus(422);
    }

    public function test_draft_is_hidden_from_public_catalogue(): void
    {
        $actor = User::factory()->create();
        $this->app->make(CreatePressPublicationAction::class)->handle(
            new PressPublicationData(
                title: 'Hidden Draft Book',
                publisherName: 'Family House Press',
                languageCode: 'en',
                format: PressPublicationFormat::Pdf,
                asDraft: true,
            ),
            $actor,
            'press-draft-hidden-0001',
        );

        $this->getJson('/api/v1/press/publications?filter[category]=missing')
            ->assertOk();

        $this->getJson('/api/v1/press/publications')
            ->assertOk()
            ->assertJsonMissing(['Hidden Draft Book']);
    }

    public function test_document_pdf_devotional_and_bible_study_can_be_created(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['press.publications.manage', 'press.publications.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'press-document-pdf-0001'])
            ->postJson('/api/v1/admin/press/publications', [
                'title' => 'Leadership Notes',
                'publisher_name' => 'Family House Press',
                'language_code' => 'en',
                'format' => 'pdf',
                'publication_type' => 'document_pdf',
            ])
            ->assertCreated()
            ->assertJsonPath('data.publication_type', 'document_pdf')
            ->assertJsonPath('data.status', 'manuscript');

        $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'press-devotional-0001'])
            ->postJson('/api/v1/admin/press/publications', [
                'title' => 'Morning Watch',
                'publisher_name' => 'Family House Press',
                'language_code' => 'en',
                'format' => 'pdf',
                'publication_type' => 'devotional',
                'type_metadata' => ['reflection' => 'Be still and know.'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.publication_type', 'devotional');

        $created = $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'press-bible-study-0001'])
            ->postJson('/api/v1/admin/press/publications', [
                'title' => 'Romans Study',
                'publisher_name' => 'Family House Press',
                'language_code' => 'en',
                'format' => 'pdf',
                'publication_type' => 'bible_study',
                'type_metadata' => ['passage' => 'Romans 8'],
            ])
            ->assertCreated();

        $this->withHeaders($this->headers($scope))
            ->getJson('/api/v1/admin/press/publications/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.publication_type', 'bible_study')
            ->assertJsonPath('data.type_metadata.passage', 'Romans 8');
    }

    public function test_review_asset_and_schedule_apply(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions([
            'press.publications.manage',
            'press.publications.transition',
            'press.publications.view',
        ], $scope);
        $this->authenticate($actor);
        $file = FileAsset::factory()->available()->create();
        $person = Person::factory()->create();

        $created = $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'press-scheduled-doc-0001'])
            ->postJson('/api/v1/admin/press/publications', [
                'title' => 'Scheduled Tract',
                'publisher_name' => 'Family House Press',
                'language_code' => 'en',
                'format' => 'pdf',
                'publication_type' => 'document_pdf',
            ])
            ->assertCreated();
        $id = $created->json('data.id');

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/press/publications/{$id}/assets", [
                'file_asset_id' => $file->public_id,
                'asset_format' => 'pdf',
                'is_required' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'pdf')
            ->assertJsonPath('data.status', 'ready');

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/press/publications/{$id}/reviews", [
                'stage' => 'editorial',
                'decision' => 'approved',
                'reviewer_person_id' => $person->public_id,
                'comments' => 'Clear teaching.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved');

        $publication = PressPublication::query()->where('public_id', $id)->firstOrFail();
        $transition = $this->app->make(TransitionPressPublicationAction::class);
        foreach ([
            PressPublicationStatus::EditorialReview,
            PressPublicationStatus::TheologicalReview,
            PressPublicationStatus::CopyEditing,
            PressPublicationStatus::Design,
            PressPublicationStatus::PublicationApproval,
        ] as $status) {
            $publication = $transition->handle($publication, $status, $actor, 'workflow.progressed');
        }

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/press/publications/{$id}/schedule", [
                'scheduled_publish_at' => now()->subMinute()->toIso8601String(),
                'reason_code' => 'schedule.publish',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $applied = $this->app->make(ApplyPressPublicationSchedulesAction::class)->handle(now()->utc());
        $this->assertGreaterThanOrEqual(1, $applied);
        $this->assertSame(PressPublicationStatus::Published, $publication->fresh()->status);
    }

    public function test_private_and_draft_never_appear_on_public_show(): void
    {
        $private = PressPublication::factory()->create([
            'title' => 'Members Only',
            'availability' => PressPublicationAvailability::Available,
            'status' => PressPublicationStatus::Published,
            'published_at' => now(),
            'visibility' => PressPublicationVisibility::Private,
        ]);

        $this->getJson("/api/v1/press/publications/{$private->public_id}")
            ->assertNotFound();
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ScopeReference $scope): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::factory()->create(['code' => $permissionCode]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);

        return $actor;
    }

    private function authenticate(User $user): void
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $this->withSession([
            'security_session_id' => $securitySession->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }

    /** @return array<string, string> */
    private function headers(ScopeReference $scope): array
    {
        return ['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key];
    }
}
