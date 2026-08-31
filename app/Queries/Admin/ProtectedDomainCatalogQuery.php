<?php

namespace App\Queries\Admin;

use App\Mission\MissionSoulJourneyStatus;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\ChurchAnnouncement;
use App\Models\ChurchDepartment;
use App\Models\ChurchGroup;
use App\Models\ChurchMembership;
use App\Models\ChurchRoleAssignment;
use App\Models\Convert;
use App\Models\CounsellingCase;
use App\Models\Crusade;
use App\Models\EvangelismActivity;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\HomeChurchAttendanceRecord;
use App\Models\MissionInvitation;
use App\Models\MissionSoulJourney;
use App\Models\MissionTeamAssignment;
use App\Models\PastoralNeed;
use App\Models\Person;
use App\Models\PrayerRequest;
use App\Models\SafeguardingIncident;
use App\Models\Testimony;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProtectedDomainCatalogQuery
{
    /** @return LengthAwarePaginator<Church> */
    public function churches(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Church::query()->with([
            'location:id,public_id,country_id,administrative_unit_id,name,address_line_one,address_line_two,locality,postal_code,timezone',
            'location.country:id,public_id,iso_code,name',
            'administrativeUnit:id,public_id,name',
        ])->withCount(['homeChurches', 'memberships', 'firstTimers', 'homeChurchApplications']);
        $this->applyChurchScope($query, $scope);
        $this->applySearch($query, $filters);
        $this->applyChurchIdFilter($query, $filters, 'id');

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<HomeChurch> */
    public function homeChurches(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HomeChurch::query()->with(['church:id,public_id,name', ...PersonDisplayName::eager('leader')])
            ->withCount('memberships');
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters);
        $this->applyStatus($query, $filters);
        $this->applyChurchIdFilter($query, $filters);
        if (isset($filters['home_church_id'])) {
            $query->where('public_id', $filters['home_church_id']);
        }

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<HomeChurchApplication> */
    public function homeChurchApplications(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HomeChurchApplication::query()->with(['church:id,public_id,name', 'homeChurch:id,public_id,name', ...PersonDisplayName::eager('applicant')]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters, 'proposed_name');
        $this->applyStatus($query, $filters);
        $this->applyHomeChurchIdFilter($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<FirstTimer> */
    public function firstTimers(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = FirstTimer::query()->with([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest('registered_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<ChurchMembership> */
    public function memberships(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ChurchMembership::query()->with([
            ...PersonDisplayName::eager(),
            'church:id,public_id,name',
            'homeChurch:id,public_id,name',
        ]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applyStatus($query, $filters);
        $this->applyHomeChurchIdFilter($query, $filters);
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest('joined_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<FollowUpTask> */
    public function followUpTasks(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = FollowUpTask::query()->with([
            'firstTimer.church:id,public_id,name',
            'firstTimer.homeChurch:id,public_id,name',
            ...PersonDisplayName::eager('firstTimer.person'),
            ...PersonDisplayName::eager('assignedTo'),
        ]);
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereHas('firstTimer', fn (Builder $firstTimerQuery) => $firstTimerQuery->whereIn('church_id', $churchIds));
        }

        $this->applyStatus($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<Crusade> */
    public function crusades(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Crusade::query()->with('location:id,public_id,name,administrative_unit_id')->withCount('soulJourneys');
        $this->applyCrusadeScope($query, $scope);
        $this->applySearch($query, $filters);
        $this->applyStatus($query, $filters);

        return $query->latest('starts_at')->paginate($perPage);
    }

    /** @return array<string, int> */
    public function followUpGaps(ScopeReference $scope): array
    {
        $query = MissionSoulJourney::query();
        $crusadeIds = $this->crusadeIds($scope);
        if ($crusadeIds !== null) {
            $query->whereIn('crusade_id', $crusadeIds);
        }
        $stalledBefore = now()->utc()->subDays(7);

        return [
            'unassigned' => (clone $query)->where('status', MissionSoulJourneyStatus::New->value)->count(),
            'never_contacted' => (clone $query)->whereNull('last_follow_up_at')->where('status', '!=', MissionSoulJourneyStatus::New->value)->count(),
            'overdue' => (clone $query)->whereIn('status', [
                MissionSoulJourneyStatus::MentorAssigned->value,
                MissionSoulJourneyStatus::FollowUpActive->value,
            ])->where(function ($inner) use ($stalledBefore): void {
                $inner->whereNull('last_follow_up_at')->orWhere('last_follow_up_at', '<', $stalledBefore);
            })->count(),
            'stalled' => (clone $query)->where('status', MissionSoulJourneyStatus::FollowUpActive->value)
                ->whereNotNull('last_follow_up_at')
                ->where('last_follow_up_at', '<', $stalledBefore)
                ->count(),
            'active_follow_ups' => (clone $query)->whereIn('status', [
                MissionSoulJourneyStatus::MentorAssigned->value,
                MissionSoulJourneyStatus::FollowUpActive->value,
            ])->count(),
        ];
    }

    /** @return LengthAwarePaginator<MissionSoulJourney> */
    public function souls(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = MissionSoulJourney::query()->with([
            'crusade:id,public_id,name',
            'connectedChurch:id,public_id,name',
            ...PersonDisplayName::eager(),
            'mentorAssignment:id,public_id,mission_soul_journey_id,mission_team_assignment_id,assigned_at,ended_at',
        ]);
        $crusadeIds = $this->crusadeIds($scope);

        if ($crusadeIds !== null) {
            $query->whereIn('crusade_id', $crusadeIds);
        }

        $this->applyStatus($query, $filters);

        return $query->latest('captured_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<MissionInvitation> */
    public function invitations(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = MissionInvitation::query()->with([
            'crusade:id,public_id,name',
            'requestedLocation:id,public_id,name',
            ...PersonDisplayName::eager('requester'),
        ]);
        $crusadeIds = $this->crusadeIds($scope);

        if ($crusadeIds !== null) {
            $query->whereIn('crusade_id', $crusadeIds);
        }

        $this->applyStatus($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<MissionTeamAssignment> */
    public function teamAssignments(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = MissionTeamAssignment::query()->with([
            'crusade:id,public_id,name',
            ...PersonDisplayName::eager(),
        ]);
        $crusadeIds = $this->crusadeIds($scope);

        if ($crusadeIds !== null) {
            $query->whereIn('crusade_id', $crusadeIds);
        }

        if (isset($filters['crusade_id'])) {
            $query->whereHas('crusade', fn (Builder $inner) => $inner->where('public_id', $filters['crusade_id']));
        }

        if (isset($filters['role_code'])) {
            $query->where('role_code', $filters['role_code']);
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where(function (Builder $inner): void {
                    $inner->whereNull('ended_at')->orWhere('ended_at', '>', now()->utc());
                });
            } elseif ($filters['status'] === 'ended') {
                $query->whereNotNull('ended_at')->where('ended_at', '<=', now()->utc());
            }
        }

        return $query->latest('assigned_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<PrayerRequest> */
    public function prayerRequests(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = PrayerRequest::query()->with([...PersonDisplayName::eager(), 'assignedTo.profile']);
        $this->applyPersonChurchScope($query, $scope);
        $this->applyStatus($query, $filters);
        $this->applySearch($query, $filters, 'subject');

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<PastoralNeed> */
    public function pastoralNeeds(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = PastoralNeed::query()->with(PersonDisplayName::eager());
        $this->applyPersonChurchScope($query, $scope);
        $this->applyStatus($query, $filters);
        $this->applySearch($query, $filters, 'summary');
        if (isset($filters['home_church_id'])) {
            $homeChurchId = HomeChurch::query()->where('public_id', $filters['home_church_id'])->value('id');
            $query->whereHas(
                'person.memberships',
                fn (Builder $membership) => $membership->where('home_church_id', $homeChurchId ?? 0),
            );
        }

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<Convert> */
    public function converts(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Convert::query()->with([
            ...PersonDisplayName::eager(),
            'church:id,public_id,name',
            'homeChurch:id,public_id,name',
        ]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applyStatus($query, $filters);
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest('converted_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<EvangelismActivity> */
    public function evangelismActivities(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = EvangelismActivity::query()->with(['church:id,public_id,name']);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters, 'title');
        $this->applyStatus($query, $filters);
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest('occurred_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<ChurchDepartment> */
    public function departments(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ChurchDepartment::query()->with([
            'church:id,public_id,name',
            ...PersonDisplayName::eager('leader'),
        ]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters);
        $this->applyStatus($query, $filters);
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<ChurchRoleAssignment> */
    public function roleAssignments(ScopeReference $scope, array $filters, int $perPage, ?string $roleType = null): LengthAwarePaginator
    {
        $query = ChurchRoleAssignment::query()->with([
            'church:id,public_id,name',
            'department:id,public_id,name',
            ...PersonDisplayName::eager(),
        ]);
        $this->applyChurchForeignKeyScope($query, $scope);
        if ($roleType !== null) {
            $query->where('role_type', $roleType);
        }
        $this->applyStatus($query, $filters);
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<CounsellingCase> */
    public function counsellingCases(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = CounsellingCase::query()->with([
            'church:id,public_id,name',
            ...PersonDisplayName::eager('client'),
            ...PersonDisplayName::eager('counselor'),
        ]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applyStatus($query, $filters);

        return $query->latest('opened_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<Testimony> */
    public function testimonies(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Testimony::query()->with([
            'church:id,public_id,name',
            ...PersonDisplayName::eager(),
        ]);
        $churchIds = $this->churchIds($scope);
        if ($churchIds !== null) {
            $query->where(function (Builder $inner) use ($churchIds): void {
                $inner->whereIn('church_id', $churchIds)->orWhereNull('church_id');
            });
        }
        $this->applySearch($query, $filters, 'title');
        $this->applyStatus($query, $filters);

        return $query->latest('submitted_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<HomeChurchAttendanceRecord> */
    public function homeChurchAttendance(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HomeChurchAttendanceRecord::query()->with(['homeChurch:id,public_id,name,church_id']);
        $churchIds = $this->churchIds($scope);
        if ($churchIds !== null) {
            $query->whereHas('homeChurch', fn (Builder $hc) => $hc->whereIn('church_id', $churchIds));
        }
        if (isset($filters['church_id'])) {
            $churchId = Church::query()->where('public_id', $filters['church_id'])->value('id');
            $query->whereHas('homeChurch', fn (Builder $hc) => $hc->where('church_id', $churchId ?? 0));
        }
        $this->applyHomeChurchIdFilter($query, $filters);

        return $query->latest('service_date')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<ChurchGroup> */
    public function churchGroups(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ChurchGroup::query()->with([
            'church:id,public_id,name',
            ...PersonDisplayName::eager('leader'),
        ]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters);
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<ChurchAnnouncement> */
    public function churchAnnouncements(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ChurchAnnouncement::query()->with([
            'church:id,public_id,name',
            ...PersonDisplayName::eager('createdBy'),
        ]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters, 'title');
        $this->applyChurchIdFilter($query, $filters);

        return $query->latest('published_at')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<Person> */
    public function people(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Person::query()->whereNull('archived_at')->with([
            'profile:id,person_id,given_name,middle_name,family_name,preferred_name,phone',
            'user:id,person_id,name,email,account_status',
            'memberships' => fn ($memberships) => $memberships->with('church:id,public_id,name')->latest('joined_at')->limit(3),
            'firstTimers' => fn ($first) => $first->latest('registered_at')->limit(3),
            'converts' => fn ($converts) => $converts->latest('converted_at')->limit(3),
            'roleAssignments' => fn ($roles) => $roles->whereNull('ended_at')->limit(6),
        ]);
        $churchIds = $this->churchIds($scope);
        if ($churchIds !== null) {
            $query->where(function (Builder $inner) use ($churchIds): void {
                $inner->whereHas('memberships', fn (Builder $m) => $m->whereIn('church_id', $churchIds))
                    ->orWhereHas('firstTimers', fn (Builder $f) => $f->whereIn('church_id', $churchIds))
                    ->orWhereHas('converts', fn (Builder $c) => $c->whereIn('church_id', $churchIds))
                    ->orWhereHas('roleAssignments', fn (Builder $r) => $r->whereIn('church_id', $churchIds));
            });
        }
        if (isset($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('public_id', 'like', $search)
                    ->orWhereHas('profile', function (Builder $profile) use ($search): void {
                        $profile->where('given_name', 'like', $search)
                            ->orWhere('family_name', 'like', $search)
                            ->orWhere('preferred_name', 'like', $search);
                    })->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $search)->orWhere('name', 'like', $search));
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<SafeguardingIncident> */
    public function safeguardingIncidents(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = SafeguardingIncident::query()->with([...PersonDisplayName::eager('subject')]);
        $this->applyStatus($query, $filters);

        return $query->latest('reported_at')->paginate($perPage);
    }

    private function applyChurchScope(Builder $query, ScopeReference $scope): void
    {
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereIn('id', $churchIds);
        }
    }

    private function applyChurchForeignKeyScope(Builder $query, ScopeReference $scope): void
    {
        $churchIds = $this->churchIds($scope);

        if ($churchIds !== null) {
            $query->whereIn('church_id', $churchIds);
        }

        if ($scope->type === 'home_church') {
            $homeChurchId = HomeChurch::query()->where('public_id', $scope->key)->value('id');
            $query->where('home_church_id', $homeChurchId ?? 0);
        }
    }

    private function applyPersonChurchScope(Builder $query, ScopeReference $scope): void
    {
        $churchIds = $this->churchIds($scope);

        if ($churchIds === null) {
            return;
        }

        $query->whereHas('person', function (Builder $personQuery) use ($churchIds): void {
            $personQuery->where(function (Builder $inner) use ($churchIds): void {
                $inner->whereHas('memberships', fn (Builder $membershipQuery) => $membershipQuery->whereIn('church_id', $churchIds))
                    ->orWhereHas('firstTimers', fn (Builder $firstTimerQuery) => $firstTimerQuery->whereIn('church_id', $churchIds));
            });
        });
    }

    private function applyCrusadeScope(Builder $query, ScopeReference $scope): void
    {
        $crusadeIds = $this->crusadeIds($scope);

        if ($crusadeIds !== null) {
            $query->whereIn('id', $crusadeIds);
        }
    }

    /** @return array<int, int>|null */
    private function churchIds(ScopeReference $scope): ?array
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return null;
        }

        $query = Church::query();

        match ($scope->type) {
            'country' => $query->whereHas('administrativeUnit.country', fn (Builder $countryQuery) => $countryQuery->where('public_id', $scope->key)),
            'administrative_unit' => $query->whereIn('administrative_unit_id', $this->administrativeUnitSubtreeIds($scope->key)),
            'church' => $query->where('public_id', $scope->key),
            'home_church' => $query->whereHas('homeChurches', fn (Builder $homeChurchQuery) => $homeChurchQuery->where('public_id', $scope->key)),
            default => $query->whereRaw('1 = 0'),
        };

        return $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @return array<int, int>|null */
    private function crusadeIds(ScopeReference $scope): ?array
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return null;
        }

        $query = Crusade::query();

        match ($scope->type) {
            'country' => $query->whereHas('location.administrativeUnit.country', fn (Builder $countryQuery) => $countryQuery->where('public_id', $scope->key)),
            'administrative_unit' => $query->whereHas('location', fn (Builder $locationQuery) => $locationQuery->whereIn('administrative_unit_id', $this->administrativeUnitSubtreeIds($scope->key))),
            'mission_crusade' => $query->where('public_id', $scope->key),
            default => $query->whereRaw('1 = 0'),
        };

        return $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @return array<int, int> */
    private function administrativeUnitSubtreeIds(string $publicId): array
    {
        $root = AdministrativeUnit::query()->select(['id', 'country_id'])->where('public_id', $publicId)->first();

        if ($root === null) {
            return [];
        }

        $units = AdministrativeUnit::query()->select(['id', 'parent_id'])->where('country_id', $root->country_id)->get();
        $allowed = [$root->getKey() => true];

        do {
            $changed = false;

            foreach ($units as $unit) {
                if (! isset($allowed[$unit->getKey()]) && isset($allowed[$unit->parent_id])) {
                    $allowed[$unit->getKey()] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        return array_map('intval', array_keys($allowed));
    }

    private function applyChurchIdFilter(Builder $query, array $filters, string $column = 'church_id'): void
    {
        if (! isset($filters['church_id'])) {
            return;
        }

        if ($column === 'id') {
            $query->where('public_id', $filters['church_id']);

            return;
        }

        $churchId = Church::query()->where('public_id', $filters['church_id'])->value('id');
        $query->where($column, $churchId ?? 0);
    }

    private function applyHomeChurchIdFilter(Builder $query, array $filters): void
    {
        if (! isset($filters['home_church_id'])) {
            return;
        }

        $homeChurchId = HomeChurch::query()->where('public_id', $filters['home_church_id'])->value('id');
        $query->where('home_church_id', $homeChurchId ?? 0);
    }

    private function applySearch(Builder $query, array $filters, string $column = 'name'): void
    {
        if (isset($filters['search'])) {
            $query->where($column, 'like', '%'.trim((string) $filters['search']).'%');
        }
    }

    private function applyStatus(Builder $query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }
}
