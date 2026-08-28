<?php

namespace App\Support\Platform;

use App\Models\FeatureFlag;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

class FeatureFlagResolver
{
    private const string RevisionCacheKey = 'platform.feature_flag.revision';

    public function __construct(private Repository $cache) {}

    public function enabled(
        PlatformKey $key,
        PlatformContext $context,
        ?FeatureRolloutKey $rolloutKey = null,
        ?CarbonInterface $at = null,
    ): bool {
        $payload = $this->resolvePayload($key->value, $context);

        if ($payload === null || ! $payload['is_enabled']) {
            return false;
        }

        $at = $at?->toImmutable() ?? CarbonImmutable::now();
        $startsAt = $payload['starts_at'] !== null
            ? CarbonImmutable::parse($payload['starts_at'])
            : null;
        $endsAt = $payload['ends_at'] !== null
            ? CarbonImmutable::parse($payload['ends_at'])
            : null;

        if (($startsAt !== null && $at->isBefore($startsAt)) || ($endsAt !== null && ! $at->isBefore($endsAt))) {
            return false;
        }

        if ($payload['rollout_percentage'] >= 100) {
            return true;
        }

        if ($payload['rollout_percentage'] === 0 || $rolloutKey === null) {
            return false;
        }

        $digest = hash('sha256', implode("\0", [$key->value, $context->hash(), $rolloutKey->value]), true);
        $bucket = unpack('Nbucket', $digest)['bucket'] % 10000;

        return $bucket < ($payload['rollout_percentage'] * 100);
    }

    public function invalidate(): void
    {
        $this->cache->forever(self::RevisionCacheKey, Str::uuid()->toString());
    }

    /**
     * @return array{is_enabled: bool, rollout_percentage: int, starts_at: string|null, ends_at: string|null}|null
     */
    private function resolvePayload(string $key, PlatformContext $context): ?array
    {
        $revision = $this->cache->get(self::RevisionCacheKey, 'initial');
        $cacheKey = 'platform.feature_flag.'.hash('sha256', implode("\0", [
            (string) $revision,
            $key,
            $context->hash(),
        ]));

        return $this->cache->remember(
            $cacheKey,
            300,
            function () use ($key, $context): ?array {
                $candidateHashes = $context->candidateHashes();
                $flags = FeatureFlag::query()
                    ->select([
                        'context_hash',
                        'is_enabled',
                        'rollout_percentage',
                        'starts_at',
                        'ends_at',
                    ])
                    ->where('key', $key)
                    ->whereIn('context_hash', $candidateHashes)
                    ->get()
                    ->keyBy('context_hash');

                foreach ($candidateHashes as $candidateHash) {
                    $flag = $flags->get($candidateHash);

                    if ($flag !== null) {
                        return [
                            'is_enabled' => $flag->is_enabled,
                            'rollout_percentage' => $flag->rollout_percentage,
                            'starts_at' => $flag->starts_at?->toIso8601String(),
                            'ends_at' => $flag->ends_at?->toIso8601String(),
                        ];
                    }
                }

                return null;
            },
        );
    }
}
