<?php

namespace App\Support\Platform;

use App\Models\PlatformConfiguration;
use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

class PlatformConfigurationResolver
{
    private const string RevisionCacheKey = 'platform.configuration.revision';

    public function __construct(
        private Repository $cache,
        private PlatformConfigurationValueCodec $codec,
    ) {}

    public function resolve(PlatformKey $key, PlatformContext $context, mixed $default = null): mixed
    {
        $revision = $this->cache->get(self::RevisionCacheKey, 'initial');
        $cacheKey = 'platform.configuration.'.hash('sha256', implode("\0", [
            (string) $revision,
            $key->value,
            $context->hash(),
        ]));

        $payload = $this->cache->remember(
            $cacheKey,
            300,
            fn (): ?array => $this->findPayload($key->value, $context),
        );

        if ($payload === null) {
            return $default;
        }

        return $this->codec->decode(
            ConfigurationValueType::from($payload['value_type']),
            ConfigurationClassification::from($payload['classification']),
            $payload['stored_value'],
        );
    }

    public function invalidate(): void
    {
        $this->cache->forever(self::RevisionCacheKey, Str::uuid()->toString());
    }

    /**
     * @return array{value_type: string, classification: string, stored_value: string}|null
     */
    private function findPayload(string $key, PlatformContext $context): ?array
    {
        $candidateHashes = $context->candidateHashes();
        $configurations = PlatformConfiguration::query()
            ->select(['context_hash', 'value_type', 'classification', 'stored_value'])
            ->where('key', $key)
            ->whereIn('context_hash', $candidateHashes)
            ->get()
            ->keyBy('context_hash');

        foreach ($candidateHashes as $candidateHash) {
            $configuration = $configurations->get($candidateHash);

            if ($configuration !== null) {
                return [
                    'value_type' => $configuration->value_type->value,
                    'classification' => $configuration->classification->value,
                    'stored_value' => $configuration->getRawOriginal('stored_value'),
                ];
            }
        }

        return null;
    }
}
