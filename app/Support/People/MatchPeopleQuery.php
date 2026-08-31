<?php

namespace App\Support\People;

use App\Models\Person;
use App\Models\User;
use App\Support\Identity\PersonDisplayName;

class MatchPeopleQuery
{
    /**
     * @return list<array{id: string, name: string, email: string|null, phone: string|null, match_reason: string}>
     */
    public function handle(?string $email, ?string $phone, ?string $givenName, ?string $familyName): array
    {
        $matches = [];
        $seen = [];

        $email = $email !== null ? strtolower(trim($email)) : '';
        if ($email !== '') {
            $user = User::query()->with('person.profile')->whereRaw('lower(email) = ?', [$email])->first();
            if ($user?->person !== null && $user->person->archived_at === null) {
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
