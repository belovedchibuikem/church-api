<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lastSession = $this->securitySessions
            ->sortByDesc('last_seen_at')
            ->first();

        return [
            'person_id' => $this->person?->public_id,
            'email' => $this->email,
            'account_status' => $this->account_status?->value,
            'member_since' => $this->created_at?->toIso8601String(),
            'last_active_at' => $lastSession?->last_seen_at?->toIso8601String(),
            'roles' => $this->roleAssignments
                ->filter(fn ($assignment): bool => $assignment->role !== null)
                ->map(fn ($assignment): string => $assignment->role->name)
                ->values()
                ->all(),
            'profile' => [
                'given_name' => $this->person?->profile?->given_name,
                'middle_name' => $this->person?->profile?->middle_name,
                'family_name' => $this->person?->profile?->family_name,
                'preferred_name' => $this->person?->profile?->preferred_name,
            ],
            'preferences' => $this->person?->preference === null
                ? null
                : [
                    'locale' => $this->person->preference->locale,
                    'timezone' => $this->person->preference->timezone,
                ],
        ];
    }
}
