<?php

namespace App\Support\Kca;

use App\Models\KcaModule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateKcaModuleAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        KcaModule $module,
        string $code,
        string $title,
        int $sequence,
        int $durationDays,
        bool $isActive,
        User $actor,
    ): KcaModule {
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

        return DB::transaction(function () use ($module, $normalizedCode, $normalizedTitle, $sequence, $durationDays, $isActive, $actor): KcaModule {
            $locked = KcaModule::query()->lockForUpdate()->findOrFail($module->getKey());

            if ($locked->published_at !== null) {
                throw new InvalidArgumentException('Published KCA modules cannot be edited. Create a new version instead.');
            }

            if (
                KcaModule::query()
                    ->where('code', $normalizedCode)
                    ->whereKeyNot($locked->getKey())
                    ->lockForUpdate()
                    ->exists()
            ) {
                throw new InvalidArgumentException('A KCA module with this code already exists.');
            }

            $locked->forceFill([
                'code' => $normalizedCode,
                'title' => $normalizedTitle,
                'sequence' => $sequence,
                'duration_days' => $durationDays,
                'is_active' => $isActive,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.module.updated',
                actor: $actor,
                targetType: 'kca_module',
                targetId: $locked->public_id,
                metadata: [
                    'code' => $normalizedCode,
                    'sequence' => $sequence,
                    'duration_days' => $durationDays,
                ],
            ));

            return $locked->refresh();
        }, attempts: 3);
    }
}
