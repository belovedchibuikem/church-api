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

class CreateKcaCohortAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        KcaYear $year,
        string $code,
        string $name,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        User $actor,
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

        return DB::transaction(function () use ($year, $normalizedCode, $normalizedName, $startsOn, $endsOn, $actor): KcaCohort {
            $lockedYear = KcaYear::query()->lockForUpdate()->findOrFail($year->getKey());

            if (
                KcaCohort::query()
                    ->whereBelongsTo($lockedYear, 'year')
                    ->where('code', $normalizedCode)
                    ->lockForUpdate()
                    ->exists()
            ) {
                throw new InvalidArgumentException('A KCA cohort with this code already exists for the year.');
            }

            $cohort = KcaCohort::query()->create([
                'kca_year_id' => $lockedYear->getKey(),
                'code' => $normalizedCode,
                'name' => $normalizedName,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.cohort.created',
                actor: $actor,
                targetType: 'kca_cohort',
                targetId: $cohort->public_id,
                metadata: [
                    'year_id' => $lockedYear->public_id,
                    'code' => $normalizedCode,
                ],
            ));

            return $cohort;
        }, attempts: 3);
    }
}
