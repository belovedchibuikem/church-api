<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\ListUserRecordsRequest;
use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\KcaEnrollment;
use App\Models\KcaFollow;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserKcaCommunityController extends Controller
{
    public function directory(ListUserRecordsRequest $request): JsonResponse
    {
        $caller = $this->requirePerson($this->actor($request));
        $q = trim((string) $request->validated('q', ''));

        $personIds = KcaEnrollment::query()->pluck('person_id')
            ->merge(
                KcaApplication::query()
                    ->where('status', KcaApplicationState::Accepted)
                    ->pluck('person_id')
            )
            ->unique()
            ->reject(fn ($id): bool => (int) $id === (int) $caller->getKey())
            ->values();

        $query = Person::query()
            ->with(['profile:id,person_id,given_name,middle_name,family_name,preferred_name,country', 'user:id,person_id,name,email'])
            ->whereIn('id', $personIds);

        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q): void {
                $builder
                    ->whereHas('profile', function (Builder $profile) use ($q): void {
                        $profile->where('preferred_name', 'like', "%{$q}%")
                            ->orWhere('given_name', 'like', "%{$q}%")
                            ->orWhere('family_name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('user', function (Builder $user) use ($q): void {
                        $user->where('name', 'like', "%{$q}%");
                    });
            });
        }

        $paginator = $query->orderBy('id')->paginate((int) $request->validated('per_page', 25));
        $followedIds = KcaFollow::query()
            ->where('follower_person_id', $caller->getKey())
            ->whereIn('followed_person_id', $paginator->getCollection()->modelKeys())
            ->pluck('followed_person_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $rows = $paginator->getCollection()->map(static function (Person $person) use ($followedIds): array {
            return [
                'id' => $person->public_id,
                'display_name' => PersonDisplayName::of($person),
                'country' => $person->profile?->country,
                'is_following' => in_array((int) $person->getKey(), $followedIds, true),
            ];
        })->values()->all();

        return $this->page($request, $paginator, $rows);
    }

    public function follow(Request $request, string $person): JsonResponse
    {
        $caller = $this->requirePerson($this->actor($request));
        $target = Person::query()->where('public_id', $person)->firstOrFail();

        if ((int) $target->getKey() === (int) $caller->getKey()) {
            throw new UnprocessableEntityHttpException('You cannot follow yourself.');
        }

        $follow = KcaFollow::query()
            ->where('follower_person_id', $caller->getKey())
            ->where('followed_person_id', $target->getKey())
            ->first();

        $created = false;
        if ($follow === null) {
            $follow = new KcaFollow;
            $follow->forceFill([
                'follower_person_id' => $caller->getKey(),
                'followed_person_id' => $target->getKey(),
            ])->save();
            $created = true;
        }

        return ApiResponse::success($request, [
            'id' => $follow->public_id,
            'person_id' => $target->public_id,
            'is_following' => true,
        ], status: $created ? 201 : 200);
    }

    public function unfollow(Request $request, string $person): JsonResponse
    {
        $caller = $this->requirePerson($this->actor($request));
        $target = Person::query()->where('public_id', $person)->firstOrFail();

        KcaFollow::query()
            ->where('follower_person_id', $caller->getKey())
            ->where('followed_person_id', $target->getKey())
            ->delete();

        return ApiResponse::success($request, [
            'person_id' => $target->public_id,
            'is_following' => false,
        ]);
    }

    public function following(ListUserRecordsRequest $request): JsonResponse
    {
        $caller = $this->requirePerson($this->actor($request));

        $paginator = KcaFollow::query()
            ->with([
                'followed:id,public_id',
                'followed.profile:id,person_id,given_name,middle_name,family_name,preferred_name,country',
                'followed.user:id,person_id,name,email',
            ])
            ->where('follower_person_id', $caller->getKey())
            ->latest('id')
            ->paginate((int) $request->validated('per_page', 25));

        $rows = $paginator->getCollection()->map(static function (KcaFollow $follow): array {
            $person = $follow->followed;

            return [
                'id' => $person?->public_id,
                'display_name' => PersonDisplayName::of($person),
                'country' => $person?->profile?->country,
                'is_following' => true,
            ];
        })->values()->all();

        return $this->page($request, $paginator, $rows);
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
