<?php

namespace App\Support\Communication\Queries;

use App\Church\ChurchMembershipStatus;
use App\Communication\CommunicationAudienceRuleType;
use App\Identity\UserAccountStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\CommunicationAudience;
use App\Models\CommunicationAudienceRule;
use App\Models\HomeChurch;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\Authorization\ScopeReference;
use App\Support\Communication\Contracts\CommunicationScopeTargetingPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CommunicationAudienceCandidateQuery
{
    public function __construct(private CommunicationScopeTargetingPolicy $scopeTargetingPolicy) {}

    /**
     * Resolve audience membership only from authoritative server-side records.
     *
     * @return Collection<int, User>
     */
    public function resolve(CommunicationAudience $audience): Collection
    {
        $rules = $audience->rules()->get();

        if ($rules->contains(fn (CommunicationAudienceRule $rule): bool => $rule->type === CommunicationAudienceRuleType::AllUsers)) {
            return $this->activeUsers()->get();
        }

        $userIds = $rules
            ->flatMap(fn (CommunicationAudienceRule $rule): Collection => $this->userIdsForRule($rule))
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return $this->activeUsers()->whereKey($userIds)->get();
    }

    /** @return Builder<User> */
    private function activeUsers(): Builder
    {
        return User::query()
            ->select(['id', 'person_id', 'name', 'email', 'account_status'])
            ->whereNotNull('person_id')
            ->where('account_status', UserAccountStatus::Active->value)
            ->with('person:id,public_id')
            ->orderBy('id');
    }

    /** @return Collection<int, int> */
    private function userIdsForRule(CommunicationAudienceRule $rule): Collection
    {
        return match ($rule->type) {
            CommunicationAudienceRuleType::Scope => $this->scopeUserIds($rule),
            CommunicationAudienceRuleType::Church => $this->churchUserIds($rule),
            CommunicationAudienceRuleType::HomeChurch => $this->homeChurchUserIds($rule),
            CommunicationAudienceRuleType::KcaCohort => $this->kcaCohortUserIds($rule),
            CommunicationAudienceRuleType::Role => $this->roleUserIds($rule),
            CommunicationAudienceRuleType::Permission => $this->permissionUserIds($rule),
            CommunicationAudienceRuleType::AllUsers,
            CommunicationAudienceRuleType::Department => collect(),
        };
    }

    /** @return Collection<int, int> */
    private function scopeUserIds(CommunicationAudienceRule $rule): Collection
    {
        if ($rule->scope_type === null || $rule->scope_key === null) {
            return collect();
        }

        $scope = new ScopeReference($rule->scope_type, $rule->scope_key);

        return $this->activeUsers()
            ->whereHas('roleAssignments', fn (Builder $query): Builder => $query
                ->active()
                ->whereHas('scopeAssignments'))
            ->get()
            ->filter(fn (User $user): bool => $this->scopeTargetingPolicy->allows($user, $scope))
            ->pluck('id');
    }

    /** @return Collection<int, int> */
    private function churchUserIds(CommunicationAudienceRule $rule): Collection
    {
        $churchId = Church::query()->where('public_id', $rule->selector_key)->value('id');

        return $this->membershipUserIds('church_id', $churchId);
    }

    /** @return Collection<int, int> */
    private function homeChurchUserIds(CommunicationAudienceRule $rule): Collection
    {
        $homeChurchId = HomeChurch::query()->where('public_id', $rule->selector_key)->value('id');

        return $this->membershipUserIds('home_church_id', $homeChurchId);
    }

    /** @return Collection<int, int> */
    private function membershipUserIds(string $column, mixed $identifier): Collection
    {
        if (! is_int($identifier)) {
            return collect();
        }

        $personIds = ChurchMembership::query()
            ->where($column, $identifier)
            ->where('status', ChurchMembershipStatus::Active->value)
            ->pluck('person_id');

        return User::query()->whereIn('person_id', $personIds)->pluck('id');
    }

    /** @return Collection<int, int> */
    private function kcaCohortUserIds(CommunicationAudienceRule $rule): Collection
    {
        $cohortId = KcaCohort::query()->where('public_id', $rule->selector_key)->value('id');

        if (! is_int($cohortId)) {
            return collect();
        }

        $personIds = KcaEnrollment::query()->where('kca_cohort_id', $cohortId)->pluck('person_id');

        return User::query()->whereIn('person_id', $personIds)->pluck('id');
    }

    /** @return Collection<int, int> */
    private function roleUserIds(CommunicationAudienceRule $rule): Collection
    {
        return RoleAssignment::query()
            ->active()
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', $rule->selector_key))
            ->pluck('user_id');
    }

    /** @return Collection<int, int> */
    private function permissionUserIds(CommunicationAudienceRule $rule): Collection
    {
        return RoleAssignment::query()
            ->active()
            ->whereHas('role.rolePermissions.permission', fn (Builder $query): Builder => $query->where('code', $rule->selector_key))
            ->pluck('user_id');
    }
}
