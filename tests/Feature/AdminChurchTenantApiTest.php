<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\CounsellingCase;
use App\Models\PastoralNeed;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ProvisionAuthorizationBundlesAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminChurchTenantApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_church_operator_only_sees_and_mutates_their_church(): void
    {
        $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();

        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $memberA = Person::factory()->withProfile()->create();
        $memberB = Person::factory()->withProfile()->create();
        $memberA->profile?->update(['given_name' => 'Ada', 'family_name' => 'Okeke']);
        $memberB->profile?->update(['given_name' => 'Bola', 'family_name' => 'Adeyemi']);
        ChurchMembership::factory()->create([
            'person_id' => $memberA->getKey(),
            'church_id' => $churchA->getKey(),
        ]);
        ChurchMembership::factory()->create([
            'person_id' => $memberB->getKey(),
            'church_id' => $churchB->getKey(),
        ]);

        $headers = $this->authenticateChurchOperator($churchA);

        $members = $this->withHeaders($headers)->getJson('/api/v1/admin/church/memberships')->assertOk()->json('data');
        $ids = collect($members)->pluck('person_id')->all();
        $this->assertContains($memberA->public_id, $ids);
        $this->assertNotContains($memberB->public_id, $ids);

        $churches = $this->withHeaders($headers)->getJson('/api/v1/admin/church/churches')->assertOk()->json('data');
        $this->assertSame([$churchA->public_id], collect($churches)->pluck('id')->all());

        $this->withHeaders($headers)->putJson('/api/v1/admin/church/people/'.$memberB->public_id, [
            'phone' => '+2348000000000',
        ])->assertNotFound();

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/people/matches', [
            'given_name' => 'Bola',
            'family_name' => 'Adeyemi',
        ])->assertOk()->assertJsonPath('data.matches', []);

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/giving-records', [
            'church_id' => $churchB->public_id,
            'person_id' => $memberB->public_id,
            'amount_minor' => 500000,
            'currency' => 'NGN',
            'purpose_code' => 'tithe',
            'idempotency_key' => 'church-giving-other-church',
        ])->assertNotFound();

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/giving-records', [
            'church_id' => $churchA->public_id,
            'person_id' => $memberA->public_id,
            'amount_minor' => 250000,
            'currency' => 'NGN',
            'purpose_code' => 'offering',
            'idempotency_key' => 'church-giving-own-church',
        ])->assertCreated()
            ->assertJsonPath('data.category', 'offering')
            ->assertJsonPath('data.amount_minor', 250000);

        $listed = $this->withHeaders($headers)->getJson('/api/v1/admin/church/giving-transactions')->assertOk();
        $this->assertSame(1, $listed->json('meta.pagination.total'));
    }

    public function test_platform_admin_still_sees_all_churches(): void
    {
        $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $headers = $this->authenticatePlatformAdmin();

        $churches = $this->withHeaders($headers)->getJson('/api/v1/admin/church/churches')->assertOk()->json('data');
        $ids = collect($churches)->pluck('id')->all();
        $this->assertContains($churchA->public_id, $ids);
        $this->assertContains($churchB->public_id, $ids);
    }

    public function test_counselling_and_pastoral_needs_stay_inside_church_scope(): void
    {
        $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $personA = Person::factory()->withProfile()->create();
        ChurchMembership::factory()->create([
            'person_id' => $personA->getKey(),
            'church_id' => $churchA->getKey(),
        ]);

        $otherCase = CounsellingCase::query()->create([
            'church_id' => $churchB->getKey(),
            'client_person_id' => $personA->getKey(),
            'case_type' => 'general',
            'status' => 'open',
            'summary' => 'Case at another church',
            'opened_at' => now()->utc(),
        ]);
        PastoralNeed::query()->create([
            'person_id' => $personA->getKey(),
            'church_id' => $churchB->getKey(),
            'category' => 'care',
            'summary' => 'Need tagged to another church',
            'status' => 'open',
        ]);
        $ownNeed = PastoralNeed::query()->create([
            'person_id' => $personA->getKey(),
            'church_id' => $churchA->getKey(),
            'category' => 'care',
            'summary' => 'Need in this church',
            'status' => 'open',
        ]);

        $headers = $this->authenticateChurchOperator($churchA, extraPermissions: ['church.follow_up.view', 'church.follow_up.complete']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/church/counselling-cases/'.$otherCase->public_id)
            ->assertNotFound();

        $needs = $this->withHeaders($headers)->getJson('/api/v1/admin/church/pastoral-needs')->assertOk()->json('data');
        $needIds = collect($needs)->pluck('id')->all();
        $this->assertContains($ownNeed->public_id, $needIds);
        $this->assertCount(1, $needIds);
    }

    /** @return array<string, string> */
    private function authenticateChurchOperator(Church $church, array $extraPermissions = []): array
    {
        $scope = new ScopeReference('church', $church->public_id);
        $actor = User::factory()->create();
        $role = Role::query()
            ->where('code', AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE)
            ->firstOrFail();
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);
        foreach ($extraPermissions as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }
        $this->authenticate($actor);

        return $this->headers($scope);
    }

    /** @return array<string, string> */
    private function authenticatePlatformAdmin(): array
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = User::factory()->create();
        $role = Role::query()
            ->where('code', AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE)
            ->firstOrFail();
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);
        $this->authenticate($actor);

        return $this->headers($scope);
    }

    private function authenticate(User $user): void
    {
        $session = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user)->withSession([
            'security_session_id' => $session->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }

    /** @return array<string, string> */
    private function headers(ScopeReference $scope): array
    {
        return [
            'X-Scope-Type' => $scope->type,
            'X-Scope-ID' => $scope->key,
        ];
    }
}
