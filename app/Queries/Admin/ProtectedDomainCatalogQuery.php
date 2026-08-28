<?php

namespace App\Queries\Admin;

use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Crusade;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\MissionInvitation;
use App\Models\MissionSoulJourney;
use App\Models\PastoralNeed;
use App\Models\PrayerRequest;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProtectedDomainCatalogQuery
{
    /** @return LengthAwarePaginator<Church> */
    public function churches(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Church::query()->with(['location:id,public_id,name', 'administrativeUnit:id,public_id,name']);
        $this->applyChurchScope($query, $scope);
        $this->applySearch($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<HomeChurch> */
    public function homeChurches(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HomeChurch::query()->with(['church:id,public_id,name', ...PersonDisplayName::eager('leader')]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters);
        $this->applyStatus($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<HomeChurchApplication> */
    public function homeChurchApplications(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HomeChurchApplication::query()->with(['church:id,public_id,name', 'homeChurch:id,public_id,name', ...PersonDisplayName::eager('applicant')]);
        $this->applyChurchForeignKeyScope($query, $scope);
        $this->applySearch($query, $filters, 'proposed_name');
        $this->applyStatus($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /** @return LengthAwarePaginator<FirstTimer> */
    public function firstTimers(ScopeReference $scope, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = FirstTimer::query()->with([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);
        $this->applyChurchForeignKeyScope($query, $scope);

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
        $query = Crusade::query()->with('location:id,public_id,name,administrative_unit_id');
        $this->applyCrusadeScope($query, $scope);
        $this->applySearch($query, $filters);

        return $query->latest('starts_at')->paginate($perPage);
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

        return $query->latest()->paginate($perPage);
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
