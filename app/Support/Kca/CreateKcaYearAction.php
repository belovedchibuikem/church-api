<?php

namespace App\Support\Kca;

use App\Models\KcaYear;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateKcaYearAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        string $code,
        string $name,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        User $actor,
    ): KcaYear {
        $normalizedCode = Str::squish($code);
        $normalizedName = Str::squish($name);

        if ($normalizedCode === '' || Str::length($normalizedCode) > 50) {
            throw new InvalidArgumentException('KCA year codes must contain between 1 and 50 characters.');
        }

        if ($normalizedName === '' || Str::length($normalizedName) > 150) {
            throw new InvalidArgumentException('KCA year names must contain between 1 and 150 characters.');
        }

        if ($endsOn->lt($startsOn)) {
            throw new InvalidArgumentException('KCA year end dates must be on or after the start date.');
        }

        return DB::transaction(function () use ($normalizedCode, $normalizedName, $startsOn, $endsOn, $actor): KcaYear {
            if (KcaYear::query()->where('code', $normalizedCode)->lockForUpdate()->exists()) {
                throw new InvalidArgumentException('A KCA year with this code already exists.');
            }

            $year = KcaYear::query()->create([
                'code' => $normalizedCode,
                'name' => $normalizedName,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.year.created',
                actor: $actor,
                targetType: 'kca_year',
                targetId: $year->public_id,
                metadata: ['code' => $normalizedCode],
            ));

            return $year;
        }, attempts: 3);
    }
}
