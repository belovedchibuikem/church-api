<?php

namespace Tests\Feature;

use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\CommunicationTemplate;
use App\Models\DataSubjectRequest;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaCertificate;
use App\Models\MinistryEvent;
use App\Models\PaymentIntent;
use App\Models\Permission;
use App\Models\PressPublication;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Services\Admin\ProtectedDomainRegistry;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminRemainingDomainCatalogApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_global_catalog_administrator_can_list_minimized_remaining_domain_records(): void
    {
        $actor = $this->actorWithPermissions([
            'kca.applications.view',
            'kca.certificates.view',
            'press.publications.view',
            'events.events.view',
            'finance.payment_intents.view',
            'communications.templates.view',
            'reporting.alert_rules.view',
            'reporting.alert_occurrences.view',
            'privacy.data_subject_requests.view',
            'platform.files.view',
        ]);
        $application = KcaApplication::factory()->create();
        $certificate = KcaCertificate::factory()->create();
        $publication = PressPublication::factory()->create(['title' => 'Kingdom Leadership']);
        $event = MinistryEvent::factory()->create(['name' => 'Youth Summit']);
        $intent = PaymentIntent::factory()->create();
        $template = CommunicationTemplate::factory()->create(['code' => 'welcome.email']);
        $rule = AlertRule::factory()->create(['code' => 'alerts.finance.gap']);
        $occurrence = AlertOccurrence::factory()->for($rule, 'rule')->create([
            'summary' => 'secret-alert-summary',
        ]);
        $privacyRequest = DataSubjectRequest::factory()->create();
        $file = FileAsset::factory()->create();
        $this->authenticate($actor);
        $headers = ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/kca/applications')
            ->assertOk()
            ->assertJsonFragment(['id' => $application->public_id, 'status' => $application->status->value]);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/kca/certificates')
            ->assertOk()
            ->assertJsonFragment(['id' => $certificate->public_id])
            ->assertJsonMissing(['verification_code_hash'])
            ->assertJsonMissing(['issuance_key_hash']);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/press/publications?filter[search]=Kingdom')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $publication->public_id)
            ->assertJsonPath('data.0.title', 'Kingdom Leadership')
            ->assertJsonMissing(['description' => $publication->description]);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/events/events')
            ->assertOk()
            ->assertJsonFragment(['id' => $event->public_id, 'name' => 'Youth Summit']);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/finance/payment-intents')
            ->assertOk()
            ->assertJsonFragment(['id' => $intent->public_id])
            ->assertJsonMissing(['idempotency_scope_hash'])
            ->assertJsonMissing(['payload_fingerprint']);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/communications/templates')
            ->assertOk()
            ->assertJsonFragment(['code' => 'welcome.email'])
            ->assertJsonMissing(['body' => $template->body]);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/reporting/alert-rules')
            ->assertOk()
            ->assertJsonFragment(['code' => 'alerts.finance.gap'])
            ->assertJsonMissing(['configuration']);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/reporting/alert-occurrences')
            ->assertOk()
            ->assertJsonFragment(['id' => $occurrence->public_id])
            ->assertJsonMissing(['secret-alert-summary'])
            ->assertJsonMissing(['summary']);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/privacy/data-subject-requests')
            ->assertOk()
            ->assertJsonFragment(['id' => $privacyRequest->public_id])
            ->assertJsonMissing(['request_notes'])
            ->assertJsonMissing(['data_categories']);

        $this->withHeaders($headers)->getJson('/api/v1/admin/catalog/platform/files')
            ->assertOk()
            ->assertJsonFragment(['id' => $file->public_id])
            ->assertJsonMissing(['object_key'])
            ->assertJsonMissing(['idempotency_key_hash']);
    }

    public function test_non_global_scope_is_forbidden_for_catalog_routes(): void
    {
        $scope = new ScopeReference('church', '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $actor = $this->actorWithPermissions(['press.publications.view'], $scope);
        PressPublication::factory()->create();
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->getJson('/api/v1/admin/catalog/press/publications')
            ->assertForbidden();
    }

    public function test_registry_exposes_only_allowlisted_catalog_keys(): void
    {
        $keys = array_keys($this->app->make(ProtectedDomainRegistry::class)->definitions());

        $this->assertContains('kca.applications', $keys);
        $this->assertContains('platform.files', $keys);
        $this->assertContains('press.publications', $keys);
        $this->assertContains('press.authors', $keys);
        $this->assertContains('press.assets', $keys);
        $this->assertContains('press.reviews', $keys);
        $this->assertContains('press.translations', $keys);
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ?ScopeReference $scope = null): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $code) {
            $permission = Permission::factory()->create(['code' => $code]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $assignment,
            $scope ?? new ScopeReference('global', 'platform'),
        );

        return $actor;
    }

    private function authenticate(User $user): void
    {
        $session = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user)->withSession([
            'security_session_id' => $session->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }
}
