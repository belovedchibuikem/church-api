<?php

namespace App\Mission\Actions;

use App\Mission\CrusadeStatus;
use App\Models\Crusade;
use App\Models\Location;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCrusadeAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, User $actor): Crusade
    {
        $name = Str::squish((string) ($attributes['name'] ?? ''));
        if ($name === '' || Str::length($name) > 191) {
            throw new InvalidArgumentException('Crusade names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use ($attributes, $name, $actor): Crusade {
            $location = isset($attributes['location_id'])
                ? Location::query()->lockForUpdate()->findOrFail($attributes['location_id'])
                : null;

            $crusade = Crusade::query()->create([
                'name' => $name,
                'code' => $this->optionalString($attributes['code'] ?? null, 50),
                'theme' => $this->optionalString($attributes['theme'] ?? null, 191),
                'purpose' => $this->optionalString($attributes['purpose'] ?? null, 500),
                'description' => $this->optionalString($attributes['description'] ?? null, 10000),
                'timezone' => $this->optionalString($attributes['timezone'] ?? null, 64),
                'status' => CrusadeStatus::Draft,
                'location_id' => $location?->getKey(),
                'starts_at' => $attributes['starts_at'] ?? null,
                'ends_at' => $attributes['ends_at'] ?? null,
                'published_at' => null,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.crusade.created',
                actor: $actor,
                targetType: 'crusade',
                targetId: $crusade->public_id,
                scopeType: 'crusade',
                scopeId: $crusade->public_id,
                metadata: array_filter([
                    'location_id' => $location?->public_id,
                ]),
            ));

            return $crusade;
        }, attempts: 3);
    }

    private function optionalString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = Str::squish((string) $value);
        if ($normalized === '') {
            return null;
        }
        if (Str::length($normalized) > $max) {
            throw new InvalidArgumentException("Field must contain at most {$max} characters.");
        }

        return $normalized;
    }
}
