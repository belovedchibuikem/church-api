<?php

namespace App\Support\Identity;

use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\Person;
use App\Models\PersonProfile;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdatePersonProfileAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{given_name: string, middle_name?: string|null, family_name: string, preferred_name?: string|null, avatar_file_asset_id?: string|null}  $attributes
     */
    public function handle(Person $person, array $attributes, ?User $actor = null): PersonProfile
    {
        return DB::transaction(function () use ($person, $attributes, $actor): PersonProfile {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $profile = $lockedPerson->profile()->firstOrFail();

            $avatarId = array_key_exists('avatar_file_asset_id', $attributes)
                ? $this->resolveAvatarFileAssetId($lockedPerson, $attributes['avatar_file_asset_id'])
                : $profile->avatar_file_asset_id;

            $profile->fill([
                'given_name' => $attributes['given_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'family_name' => $attributes['family_name'],
                'preferred_name' => $attributes['preferred_name'] ?? null,
                'avatar_file_asset_id' => $avatarId,
            ]);

            if (! $profile->isDirty()) {
                return $profile;
            }

            $changedFields = array_keys($profile->getDirty());
            $profile->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.profile.updated',
                actor: $actor,
                targetType: 'person',
                targetId: $lockedPerson->public_id,
                metadata: ['changed_fields' => $changedFields],
            ));

            return $profile;
        }, attempts: 3);
    }

    private function resolveAvatarFileAssetId(Person $person, mixed $publicId): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        if (! is_string($publicId)) {
            throw new InvalidArgumentException('avatar_file_asset_id must be a file asset public id.');
        }

        $asset = FileAsset::query()
            ->where('public_id', $publicId)
            ->where('owner_person_id', $person->getKey())
            ->whereNull('deleted_at')
            ->first();

        if ($asset === null) {
            throw new InvalidArgumentException('Avatar file was not found for this account.');
        }

        if ($asset->status !== FileAssetStatus::Available) {
            throw new InvalidArgumentException('Avatar file must be available before it can be set on the profile.');
        }

        return (int) $asset->getKey();
    }
}
