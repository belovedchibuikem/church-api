<?php

namespace App\Support\Kca;

use App\Models\KcaModule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateKcaModuleAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(string $code, string $title, int $sequence, int $durationDays, User $actor): KcaModule
    {
        $normalizedCode = Str::squish($code);
        $normalizedTitle = Str::squish($title);

        if ($normalizedCode === '' || Str::length($normalizedCode) > 50) {
            throw new InvalidArgumentException('KCA module codes must contain between 1 and 50 characters.');
        }

        if ($normalizedTitle === '' || Str::length($normalizedTitle) > 191) {
            throw new InvalidArgumentException('KCA module titles must contain between 1 and 191 characters.');
        }

        if ($sequence < 1 || $sequence > 65535) {
            throw new InvalidArgumentException('KCA module sequences must be between 1 and 65535.');
        }
        if ($durationDays < 1 || $durationDays > 365) {
            throw new InvalidArgumentException('KCA module duration_days must be between 1 and 365.');
        }

        return DB::transaction(function () use ($normalizedCode, $normalizedTitle, $sequence, $durationDays, $actor): KcaModule {
            if (KcaModule::query()->where('code', $normalizedCode)->lockForUpdate()->exists()) {
                throw new InvalidArgumentException('A KCA module with this code already exists.');
            }

            $module = KcaModule::query()->create([
                'code' => $normalizedCode,
                'title' => $normalizedTitle,
                'sequence' => $sequence,
                'duration_days' => $durationDays,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.module.created',
                actor: $actor,
                targetType: 'kca_module',
                targetId: $module->public_id,
                metadata: [
                    'code' => $normalizedCode,
                    'sequence' => $sequence,
                    'duration_days' => $durationDays,
                ],
            ));

            return $module;
        }, attempts: 3);
    }
}
