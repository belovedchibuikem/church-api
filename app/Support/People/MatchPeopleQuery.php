<?php

namespace App\Support\People;

use App\Models\Person;
use App\Models\User;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Database\Eloquent\Builder;

class MatchPeopleQuery
{
    /**
     * @param  array<int, int>|null  $churchIds  null = unrestricted (global); empty = no matches
     * @return list<array{id: string, name: string, email: string|null, phone: string|null, match_reason: string}>
     */
    public function handle(?string $email, ?string $phone, ?string $givenName, ?string $familyName, ?array $churchIds = null): array
    {
        $matches = [];
        $seen = [];

        $email = $email !== null ? strtolower(trim($email)) : '';
        if ($email !== '') {
            $user = User::query()->with('person.profile')->whereRaw('lower(email) = ?', [$email])->first();
            if ($user?->person !== null && $user->person->archived_at === null && $this->personInChurchScope($user->person, $churchIds)) {
                $seen[$user->person->getKey()] = true;
                $matches[] = $this->row($user->person, 'email');
            }
        }

        $given = trim((string) $givenName);
        $family = trim((string) $familyName);
        if ($given !== '' && $family !== '') {
            $people = Person::query()
                ->with(['profile', 'user'])
                ->whereNull('archived_at')
                ->whereHas('profile', function ($query) use ($given, $family): void {
                    $query->where('given_name', $given)->where('family_name', $family);
                })
                ->when($churchIds !== null, fn (Builder $query) => $this->constrainToChurches($query, $churchIds ?? []))
                ->limit(10)
                ->get();
            foreach ($people as $person) {
                if (isset($seen[$person->getKey()])) {
                    continue;
                }
                $seen[$person->getKey()] = true;
                $matches[] = $this->row($person, 'name');
            }
        }

        return $matches;
    }

    /** @param  array<int, int>|null  $churchIds */
    private function personInChurchScope(Person $person, ?array $churchIds): bool
    {
        if ($churchIds === null) {
            return true;
        }

        return $this->constrainToChurches(Person::query()->whereKey($person->getKey()), $churchIds)->exists();
    }

    /**
     * @param  Builder<Person>  $query
     * @param  array<int, int>  $churchIds
     * @return Builder<Person>
     */
    private function constrainToChurches(Builder $query, array $churchIds): Builder
    {
        if ($churchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($churchIds): void {
            $inner->whereHas('memberships', fn (Builder $membershipQuery) => $membershipQuery->whereIn('church_id', $churchIds))
                ->orWhereHas('firstTimers', fn (Builder $firstTimerQuery) => $firstTimerQuery->whereIn('church_id', $churchIds))
                ->orWhereHas('converts', fn (Builder $convertQuery) => $convertQuery->whereIn('church_id', $churchIds))
                ->orWhereHas('roleAssignments', fn (Builder $roleQuery) => $roleQuery->whereIn('church_id', $churchIds));
        });
    }

    /** @return array{id: string, name: string, email: string|null, phone: string|null, match_reason: string} */
    private function row(Person $person, string $reason): array
    {
        return [
            'id' => $person->public_id,
            'name' => PersonDisplayName::of($person) ?: 'Unnamed person',
            'email' => PersonDisplayName::email($person),
            'phone' => PersonDisplayName::phone($person),
            'match_reason' => $reason,
        ];
    }
}
