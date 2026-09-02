<?php

namespace App\Support\Kca;

use App\Models\KcaOrientationStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SyncKcaOrientationStepsAction
{
    /**
     * @param  list<array<string, mixed>>  $steps
     * @return Collection<int, KcaOrientationStep>
     */
    public function handle(array $steps): Collection
    {
        return DB::transaction(function () use ($steps): Collection {
            $seenSlugs = [];
            $saved = collect();

            foreach ($steps as $index => $payload) {
                $slug = (string) ($payload['slug'] ?? '');
                if ($slug === '' || isset($seenSlugs[$slug])) {
                    throw new InvalidArgumentException('Each orientation step must have a unique slug.');
                }
                $seenSlugs[$slug] = true;

                $id = isset($payload['id']) ? (string) $payload['id'] : null;
                $step = $id === null || $id === ''
                    ? new KcaOrientationStep
                    : KcaOrientationStep::query()->where('public_id', $id)->firstOrFail();

                if ($step->exists && $step->slug !== $slug) {
                    throw new InvalidArgumentException('Orientation step slugs cannot be changed after creation.');
                }

                $step->fill([
                    'slug' => $slug,
                    'title' => (string) ($payload['title'] ?? ''),
                    'subtitle' => isset($payload['subtitle']) ? (string) $payload['subtitle'] : null,
                    'body' => isset($payload['body']) ? (string) $payload['body'] : null,
                    'display_type' => (string) ($payload['display_type'] ?? 'content'),
                    'sequence' => (int) ($payload['sequence'] ?? ($index + 1)),
                    'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
                ]);
                $step->save();
                $saved->push($step->refresh());
            }

            return $saved->sortBy('sequence')->values();
        }, attempts: 3);
    }
}
