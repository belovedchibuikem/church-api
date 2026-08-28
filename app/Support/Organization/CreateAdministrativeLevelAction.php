<?php

namespace App\Support\Organization;

use App\Models\AdministrativeLevel;
use App\Models\Country;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateAdministrativeLevelAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Country $country,
        string $code,
        string $name,
        int $sortOrder,
        ?User $actor = null,
    ): AdministrativeLevel {
        $levelCode = new AdministrativeLevelCode($code);
        $normalizedName = Str::squish($name);

        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Administrative level names must contain between 1 and 191 characters.');
        }

        if ($sortOrder < 1 || $sortOrder > 65535) {
            throw new InvalidArgumentException('Administrative level sort order must be between 1 and 65535.');
        }

        return DB::transaction(function () use (
            $country,
            $levelCode,
            $normalizedName,
            $sortOrder,
            $actor,
        ): AdministrativeLevel {
            $lockedCountry = Country::query()->lockForUpdate()->findOrFail($country->getKey());
            $conflictingLevel = AdministrativeLevel::query()
                ->whereBelongsTo($lockedCountry)
                ->where(function (Builder $query) use ($levelCode, $sortOrder): void {
                    $query->where('code', $levelCode->value)->orWhere('sort_order', $sortOrder);
                })
                ->lockForUpdate()
                ->first();

            if ($conflictingLevel !== null) {
                if (
                    $conflictingLevel->code === $levelCode->value
                    && $conflictingLevel->name === $normalizedName
                    && $conflictingLevel->sort_order === $sortOrder
                ) {
                    return $conflictingLevel;
                }

                throw new InvalidArgumentException('Administrative level code and sort order must be unique within a country.');
            }

            $level = AdministrativeLevel::query()->create([
                'country_id' => $lockedCountry->getKey(),
                'code' => $levelCode->value,
                'name' => $normalizedName,
                'sort_order' => $sortOrder,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.administrative_level.created',
                actor: $actor,
                targetType: 'administrative_level',
                targetId: $level->public_id,
                scopeType: 'country',
                scopeId: $lockedCountry->public_id,
                metadata: [
                    'code' => $level->code,
                    'sort_order' => $level->sort_order,
                ],
            ));

            return $level;
        }, attempts: 3);
    }
}
