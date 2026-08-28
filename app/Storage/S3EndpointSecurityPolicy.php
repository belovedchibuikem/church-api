<?php

namespace App\Storage;

use App\Exceptions\UnsafeObjectStorageEndpointException;
use App\Models\ObjectStorageConfiguration;
use App\Storage\Contracts\HostnameResolver;

class S3EndpointSecurityPolicy
{
    private readonly HostnameResolver $resolver;

    /** @param list<int> $allowedPorts */
    public function __construct(?HostnameResolver $resolver = null, private readonly array $allowedPorts = [443])
    {
        $this->resolver = $resolver ?? new SystemHostnameResolver;
    }

    public function assertConfigurationIsSafe(ObjectStorageConfiguration $configuration): void
    {
        $this->assertUrlIsSafe($configuration->endpoint, 'endpoint', allowPath: false);
        $this->assertUrlIsSafe($configuration->url, 'url', allowPath: true);
    }

    public function assertUrlIsSafe(?string $url, string $field = 'endpoint', bool $allowPath = false): void
    {
        if ($url === null || trim($url) === '') {
            return;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            $this->reject($field, 'must use HTTPS');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            $this->reject($field, 'must not contain credentials, a query, or a fragment');
        }

        $path = (string) ($parts['path'] ?? '');

        if (! $allowPath && $path !== '' && $path !== '/') {
            $this->reject($field, 'must be an origin without a path');
        }

        $port = (int) ($parts['port'] ?? 443);

        if (! in_array($port, $this->allowedPorts, true)) {
            $this->reject($field, 'uses a port that is not allowed');
        }

        $hostname = strtolower(rtrim(trim((string) ($parts['host'] ?? ''), '[]'), '.'));

        if (
            $hostname === ''
            || $hostname === 'localhost'
            || str_ends_with($hostname, '.localhost')
            || in_array($hostname, ['metadata.google.internal', 'metadata.google', 'instance-data', 'instance-data.ec2.internal'], true)
        ) {
            $this->reject($field, 'uses a blocked hostname');
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) === false && preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/', $hostname) !== 1) {
            $this->reject($field, 'contains an invalid hostname');
        }

        $addresses = filter_var($hostname, FILTER_VALIDATE_IP) !== false
            ? [$hostname]
            : $this->resolver->resolve($hostname);

        if ($addresses === []) {
            $this->reject($field, 'hostname did not resolve');
        }

        foreach ($addresses as $address) {
            $normalizedAddress = $this->normalizeIpAddress($address);

            if (
                $normalizedAddress === null
                || filter_var(
                    $normalizedAddress,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
                ) === false
            ) {
                $this->reject($field, 'resolves to a private, loopback, link-local, metadata, or reserved address');
            }
        }
    }

    private function normalizeIpAddress(string $address): ?string
    {
        $normalized = strtolower(trim($address, '[]'));

        if (str_starts_with($normalized, '::ffff:')) {
            $mappedIpv4 = substr($normalized, 7);

            return filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                ? $mappedIpv4
                : null;
        }

        return filter_var($normalized, FILTER_VALIDATE_IP) !== false ? $normalized : null;
    }

    private function reject(string $field, string $reason): never
    {
        throw new UnsafeObjectStorageEndpointException("The S3 {$field} {$reason}.");
    }
}
