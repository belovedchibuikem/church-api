<?php

namespace Tests\Feature;

use App\Files\FileAssetStatus;
use App\Kca\KcaApplicationState;
use App\Models\Church;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaGovernanceConfiguration;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KcaAdmissionLetterApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_preview_includes_governance_letter_assets(): void
    {
        $person = Person::factory()->withProfile()->create();
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);
        $letterhead = FileAsset::factory()->create([
            'purpose' => 'kca_admission_letterhead',
            'status' => FileAssetStatus::Pending,
        ]);
        $signature = FileAsset::factory()->create([
            'purpose' => 'kca_admission_signature',
            'status' => FileAssetStatus::Pending,
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
            'admission_letterhead_file_asset_id' => $letterhead->getKey(),
            'admission_signature_file_asset_id' => $signature->getKey(),
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->getJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.letterhead_file_asset_id', $letterhead->public_id)
            ->assertJsonPath('data.signature_file_asset_id', $signature->public_id);

        $this->withHeaders($this->headers($scope))
            ->get("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/assets/{$letterhead->public_id}")
            ->assertOk();

        $letterhead->refresh();
        $this->assertSame(FileAssetStatus::Available, $letterhead->status);
    }

    public function test_admin_can_preview_admission_letter_before_issue(): void
    {
        $person = Person::factory()->withProfile()->create();
        $person->profile?->forceFill([
            'given_name' => 'John',
            'family_name' => 'Onyeuwaoma',
        ])->save();
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
            'application_data' => [
                'church_id' => Church::factory()->create(['name' => 'Grace Chapel'])->public_id,
            ],
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->getJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.applicant_name', 'John Onyeuwaoma')
            ->assertJsonPath('data.signer_name', 'Provost Jane')
            ->assertJsonPath('data.reference_code', fn (mixed $value): bool => is_string($value) && str_ends_with($value, '/00001'))
            ->assertJsonPath('data.letter_body', fn (mixed $value): bool => is_string($value) && ! str_contains($value, 'Ref. No.: Pending'));
    }

    public function test_provisionally_accepted_application_can_be_issued_admission_letter(): void
    {
        $person = Person::factory()->create();
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::ProvisionallyAccepted,
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/issue")
            ->assertCreated()
            ->assertJsonPath('data.status', 'issued');
    }

    public function test_admin_can_download_issued_admission_letter_pdf(): void
    {
        $person = Person::factory()->create();
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition', 'kca.applications.view'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/issue")
            ->assertCreated();

        $response = $this->withHeaders($this->headers($scope))
            ->get("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/download");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent() ?: '');
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_applicant_can_accept_issued_admission_letter(): void
    {
        $person = Person::factory()->withProfile()->create();
        $user = User::factory()->create(['person_id' => $person->getKey()]);
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);
        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/issue")
            ->assertCreated()
            ->assertJsonPath('data.reference_code', fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'KCA/ADM/'));

        $this->authenticate($user);
        $this->postJson('/api/v1/user/kca/admission-letter/accept', [
            'applicant_signature_name' => 'John Accepted',
        ])
            ->assertOk()
            ->assertJsonPath('data.acceptance_status', 'accepted')
            ->assertJsonPath('data.applicant_signature_name', 'John Accepted');
    }

    public function test_accepted_applicant_can_view_issued_admission_letter(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->getKey()]);
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
            'application_data' => [
                'church_id' => Church::factory()->create(['name' => 'Grace Chapel'])->public_id,
            ],
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/issue")
            ->assertCreated()
            ->assertJsonPath('data.signer_name', 'Provost Jane');

        $this->authenticate($user);

        $this->getJson('/api/v1/user/kca/admission-letter')
            ->assertOk()
            ->assertJsonPath('data.reference_code', fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'KCA/ADM/'))
            ->assertJsonPath('data.church_name', 'Grace Chapel')
            ->assertJsonPath('data.applicant_name', PersonDisplayName::of($person) ?: 'Applicant');
    }

    public function test_kca_access_keeps_admitted_applicant_on_letter_until_acceptance(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->getKey()]);
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);
        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/issue")
            ->assertCreated();

        $this->authenticate($user);
        $this->getJson('/api/v1/user/kca/me')
            ->assertOk()
            ->assertJsonPath('data.destination', 'admission_letter')
            ->assertJsonPath('data.admission_letter.acceptance_status', 'pending')
            ->assertJsonPath('data.permitted_actions', fn (mixed $value): bool => is_array($value) && in_array('accept_letter', $value, true));
    }

    public function test_kca_access_moves_admitted_applicant_to_orientation_after_acceptance(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->getKey()]);
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Provost Jane',
            'admission_signer_title' => 'Provost, KCA',
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);
        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/applications/{$application->public_id}/admission-letter/issue")
            ->assertCreated();

        $this->authenticate($user);
        $this->postJson('/api/v1/user/kca/admission-letter/accept', [
            'applicant_signature_name' => 'Accepted Applicant',
        ])->assertOk();

        $this->getJson('/api/v1/user/kca/me')
            ->assertOk()
            ->assertJsonPath('data.destination', 'orientation')
            ->assertJsonPath('data.admission_letter.acceptance_status', 'accepted')
            ->assertJsonPath('data.permitted_actions', fn (mixed $value): bool => is_array($value) && in_array('complete_orientation', $value, true));
    }

    public function test_applications_catalog_resolves_church_name_from_church_id(): void
    {
        $church = Church::factory()->create(['name' => 'Living Word Assembly']);
        KcaApplication::factory()->create([
            'application_data' => ['church_id' => $church->public_id],
        ]);

        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.view'], $scope);
        $this->authenticate($actor);

        $response = $this->withHeaders($this->headers($scope))
            ->getJson('/api/v1/admin/catalog/kca/applications')
            ->assertOk();

        $this->assertSame('Living Word Assembly', $response->json('data.0.church_name'));
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
