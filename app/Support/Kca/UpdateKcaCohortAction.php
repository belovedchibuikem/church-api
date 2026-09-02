<?php

namespace App\Support\Kca;

use App\Models\KcaCohort;
use App\Models\KcaYear;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateKcaCohortAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        KcaCohort $cohort,
        string $code,
        string $name,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        User $actor,
        ?KcaYear $year = null,
        ?string $timezone = null,
    ): KcaCohort {
        $normalizedCode = Str::squish($code);
        $normalizedName = Str::squish($name);

        if ($normalizedCode === '' || Str::length($normalizedCode) > 50) {
            throw new InvalidArgumentException('KCA cohort codes must contain between 1 and 50 characters.');
        }

        if ($normalizedName === '' || Str::length($normalizedName) > 150) {
            throw new InvalidArgumentException('KCA cohort names must contain between 1 and 150 characters.');
        }

        if ($endsOn->lt($startsOn)) {
            throw new InvalidArgumentException('KCA cohort end dates must be on or after the start date.');
        }

        $normalizedTimezone = $timezone === null || $timezone === '' ? null : $timezone;

        return DB::transaction(function () use ($cohort, $year, $normalizedCode, $normalizedName, $startsOn, $endsOn, $actor, $normalizedTimezone): KcaCohort {
            $locked = KcaCohort::query()->lockForUpdate()->findOrFail($cohort->getKey());
            $targetYear = $year ?? $locked->year()->lockForUpdate()->firstOrFail();

            if (
                KcaCohort::query()
                    ->whereBelongsTo($targetYear, 'year')
                    ->where('code', $normalizedCode)
                    ->whereKeyNot($locked->getKey())
                    ->lockForUpdate()
                    ->exists()
            ) {
                throw new InvalidArgumentException('A KCA cohort with this code already exists for the year.');
            }

            $locked->forceFill([
                'kca_year_id' => $targetYear->getKey(),
                'code' => $normalizedCode,
                'name' => $normalizedName,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);

            if ($normalizedTimezone !== null) {
                $locked->timezone = $normalizedTimezone;
            }

            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.cohort.updated',
                actor: $actor,
                targetType: 'kca_cohort',
                targetId: $locked->public_id,
                metadata: [
                    'year_id' => $targetYear->public_id,
                    'code' => $normalizedCode,
                ],
            ));

            return $locked->refresh();
        }, attempts: 3);
    }
}
