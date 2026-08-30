<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Church\ChurchGroupMembershipStatus;
use App\Church\ChurchMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\ListUserRecordsRequest;
use App\Models\ChurchAnnouncement;
use App\Models\ChurchDocument;
use App\Models\ChurchGroup;
use App\Models\ChurchGroupMembership;
use App\Models\ChurchMembership;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserChurchCommunityController extends Controller
{
    public function listGroups(ListUserRecordsRequest $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $churchIds = $this->activeChurchIds($person);

        $paginator = ChurchGroup::query()
            ->with(['church:id,public_id,name'])
            ->withCount(['memberships as member_count' => fn ($query) => $query->where('status', ChurchGroupMembershipStatus::Active)])
            ->where('is_published', true)
            ->whereIn('church_id', $churchIds)
            ->latest('created_at')
            ->paginate((int) $request->validated('per_page', 25));

        $groupIds = $paginator->getCollection()->modelKeys();
        $membershipByGroup = ChurchGroupMembership::query()
            ->where('person_id', $person->getKey())
            ->whereIn('church_group_id', $groupIds)
            ->where('status', '!=', ChurchGroupMembershipStatus::Left)
            ->get()
            ->keyBy('church_group_id');

        $rows = $paginator->getCollection()->map(function (ChurchGroup $group) use ($membershipByGroup): array {
            $membership = $membershipByGroup->get($group->getKey());

            return [
                'id' => $group->public_id,
                'name' => $group->name,
                'description' => $group->description,
                'church_id' => $group->church?->public_id,
                'church_name' => $group->church?->name,
                'member_count' => (int) $group->member_count,
                'membership_status' => $membership?->status->value,
                'capacity' => $group->capacity,
            ];
        })->values()->all();

        return $this->page($request, $paginator, $rows);
    }

    public function showGroup(Request $request, string $group): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $churchIds = $this->activeChurchIds($person);
        $target = ChurchGroup::query()
            ->with(['church:id,public_id,name'])
            ->withCount(['memberships as member_count' => fn ($query) => $query->where('status', ChurchGroupMembershipStatus::Active)])
            ->where('public_id', $group)
            ->where('is_published', true)
            ->whereIn('church_id', $churchIds)
            ->firstOrFail();

        $membership = ChurchGroupMembership::query()
            ->where('church_group_id', $target->getKey())
            ->where('person_id', $person->getKey())
            ->where('status', '!=', ChurchGroupMembershipStatus::Left)
            ->first();

        return ApiResponse::success($request, [
            'id' => $target->public_id,
            'name' => $target->name,
            'description' => $target->description,
            'church_id' => $target->church?->public_id,
            'church_name' => $target->church?->name,
            'member_count' => (int) $target->member_count,
            'membership_status' => $membership?->status->value,
            'capacity' => $target->capacity,
        ]);
    }

    public function joinGroup(Request $request, string $group): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $churchIds = $this->activeChurchIds($person);
        $target = ChurchGroup::query()
            ->where('public_id', $group)
            ->where('is_published', true)
            ->whereIn('church_id', $churchIds)
            ->firstOrFail();

        $membership = ChurchGroupMembership::query()
            ->where('church_group_id', $target->getKey())
            ->where('person_id', $person->getKey())
            ->first();

        if ($membership === null) {
            $membership = new ChurchGroupMembership;
            $membership->forceFill([
                'church_group_id' => $target->getKey(),
                'person_id' => $person->getKey(),
                'status' => ChurchGroupMembershipStatus::Active,
                'joined_at' => now()->utc(),
                'left_at' => null,
            ])->save();
            $status = 201;
        } elseif ($membership->status === ChurchGroupMembershipStatus::Left) {
            $membership->forceFill([
                'status' => ChurchGroupMembershipStatus::Active,
                'joined_at' => now()->utc(),
                'left_at' => null,
            ])->save();
            $status = 200;
        } else {
            $status = 200;
        }

        return ApiResponse::success($request, [
            'id' => $membership->public_id,
            'group_id' => $target->public_id,
            'status' => $membership->status->value,
            'joined_at' => $membership->joined_at?->utc()->toIso8601String(),
        ], status: $status);
    }

    public function leaveGroup(Request $request, string $group): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $churchIds = $this->activeChurchIds($person);
        $target = ChurchGroup::query()
            ->where('public_id', $group)
            ->whereIn('church_id', $churchIds)
            ->firstOrFail();

        $membership = ChurchGroupMembership::query()
            ->where('church_group_id', $target->getKey())
            ->where('person_id', $person->getKey())
            ->where('status', '!=', ChurchGroupMembershipStatus::Left)
            ->firstOrFail();

        $membership->forceFill([
            'status' => ChurchGroupMembershipStatus::Left,
            'left_at' => now()->utc(),
        ])->save();

        return ApiResponse::success($request, [
            'id' => $membership->public_id,
            'group_id' => $target->public_id,
            'status' => $membership->status->value,
            'left_at' => $membership->left_at?->utc()->toIso8601String(),
        ]);
    }

    public function listAnnouncements(ListUserRecordsRequest $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $churchIds = $this->activeChurchIds($person);
        $now = now()->utc();

        $paginator = ChurchAnnouncement::query()
            ->with(['church:id,public_id,name'])
            ->whereIn('church_id', $churchIds)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->latest('published_at')
            ->paginate((int) $request->validated('per_page', 25));

        $rows = $paginator->getCollection()->map(static fn (ChurchAnnouncement $announcement): array => [
            'id' => $announcement->public_id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'church_id' => $announcement->church?->public_id,
            'church_name' => $announcement->church?->name,
            'published_at' => $announcement->published_at?->utc()->toIso8601String(),
        ])->values()->all();

        return $this->page($request, $paginator, $rows);
    }

    public function listDocuments(ListUserRecordsRequest $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $churchIds = $this->activeChurchIds($person);
        $now = now()->utc();

        $paginator = ChurchDocument::query()
            ->with(['church:id,public_id,name', 'fileAsset:id,public_id'])
            ->whereIn('church_id', $churchIds)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->latest('published_at')
            ->paginate((int) $request->validated('per_page', 25));

        $rows = $paginator->getCollection()->map(static fn (ChurchDocument $document): array => [
            'id' => $document->public_id,
            'title' => $document->title,
            'description' => $document->description,
            'church_id' => $document->church?->public_id,
            'church_name' => $document->church?->name,
            'file_asset_id' => $document->fileAsset?->public_id,
            'published_at' => $document->published_at?->utc()->toIso8601String(),
        ])->values()->all();

        return $this->page($request, $paginator, $rows);
    }

    /**
     * @return list<int>
     */
    private function activeChurchIds(Person $person): array
    {
        return ChurchMembership::query()
            ->where('person_id', $person->getKey())
            ->where('status', ChurchMembershipStatus::Active)
            ->pluck('church_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function requirePerson(User $user): Person
    {
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }

        return $person;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function page(ListUserRecordsRequest $request, mixed $paginator, array $rows): JsonResponse
    {
        return ApiResponse::success($request, $rows, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
