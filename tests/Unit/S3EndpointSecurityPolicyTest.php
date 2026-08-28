<?php

namespace Tests\Unit;

use App\Exceptions\UnsafeObjectStorageEndpointException;
use App\Models\ObjectStorageConfiguration;
use App\Storage\Contracts\HostnameResolver;
use App\Storage\ObjectStorageDriver;
use App\Storage\S3EndpointSecurityPolicy;
use App\Storage\S3FilesystemConfigurationFactory;
use App\Storage\S3ObjectStorageConnectionValidator;
use Illuminate\Filesystem\FilesystemManager;
use Mockery;
use PHPUnit\Framework\TestCase;

class S3EndpointSecurityPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_accepts_https_default_port_when_every_resolved_address_is_public(): void
    {
        $resolver = new FakeHostnameResolver([
            'objects.example.com' => ['93.184.216.34', '2606:4700:4700::1111'],
        ]);

        (new S3EndpointSecurityPolicy($resolver))->assertUrlIsSafe(
            'https://objects.example.com',
        );

        $this->assertSame(['objects.example.com'], $resolver->resolvedHostnames);
    }

    public function test_rejects_non_https_credentials_paths_queries_and_unapproved_ports_before_resolution(): void
    {
        $resolver = new FakeHostnameResolver([]);
        $policy = new S3EndpointSecurityPolicy($resolver);

        foreach ([
            'http://objects.example.com',
            'https://user:secret@objects.example.com',
            'https://objects.example.com/private-path',
            'https://objects.example.com?target=internal',
            'https://objects.example.com:8443',
        ] as $unsafeUrl) {
            try {
                $policy->assertUrlIsSafe($unsafeUrl);
                $this->fail("Expected {$unsafeUrl} to be rejected.");
            } catch (UnsafeObjectStorageEndpointException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], $resolver->resolvedHostnames);
    }

    public function test_rejects_direct_loopback_private_link_local_metadata_and_ipv4_mapped_addresses(): void
    {
        $policy = new S3EndpointSecurityPolicy(new FakeHostnameResolver([]));

        foreach ([
            'https://127.0.0.1',
            'https://10.0.0.10',
            'https://169.254.169.254',
            'https://[::1]',
            'https://[fe80::1]',
            'https://[::ffff:127.0.0.1]',
            'https://metadata.google.internal',
            'https://instance-data.ec2.internal',
        ] as $unsafeUrl) {
            $this->expectUnsafeEndpoint($policy, $unsafeUrl);
        }
    }

    public function test_rejects_a_hostname_if_any_dns_answer_is_not_public(): void
    {
        $policy = new S3EndpointSecurityPolicy(new FakeHostnameResolver([
            'rebinding.example.com' => ['93.184.216.34', '169.254.169.254'],
            'private.example.com' => ['192.168.1.10'],
            'unresolved.example.com' => [],
        ]));

        foreach ([
            'https://rebinding.example.com',
            'https://private.example.com',
            'https://unresolved.example.com',
        ] as $unsafeUrl) {
            $this->expectUnsafeEndpoint($policy, $unsafeUrl);
        }
    }

    public function test_allows_a_path_only_for_the_non_request_public_url_field(): void
    {
        $policy = new S3EndpointSecurityPolicy(new FakeHostnameResolver([
            'cdn.example.com' => ['93.184.216.34'],
        ]));

        $policy->assertUrlIsSafe('https://cdn.example.com/assets', 'url', allowPath: true);

        $this->addToAssertionCount(1);
    }

    public function test_filesystem_configuration_disables_redirects_and_sets_bounded_timeouts(): void
    {
        $configuration = new class(['driver' => ObjectStorageDriver::S3, 'access_key_id' => 'access-key', 'secret_access_key' => 'secret-key', 'region' => 'us-east-1', 'bucket' => 'safe-bucket', 'endpoint' => 'https://objects.example.com', 'url' => null, 'root_prefix' => null, 'use_path_style_endpoint' => true]) extends ObjectStorageConfiguration
        {
            /** @param array<string, mixed> $values */
            public function __construct(private readonly array $values = [])
            {
                parent::__construct();
            }

            public function getAttribute($key): mixed
            {
                return $this->values[$key] ?? null;
            }
        };
        $policy = new S3EndpointSecurityPolicy(new FakeHostnameResolver([
            'objects.example.com' => ['93.184.216.34'],
        ]));

        $diskConfiguration = (new S3FilesystemConfigurationFactory($policy))->make($configuration);

        $this->assertFalse($diskConfiguration['http']['allow_redirects']);
        $this->assertSame(5, $diskConfiguration['http']['connect_timeout']);
        $this->assertSame(15, $diskConfiguration['http']['timeout']);
    }

    public function test_connection_validation_rejects_an_unsafe_endpoint_before_building_a_disk(): void
    {
        $configuration = new class(['driver' => ObjectStorageDriver::S3, 'endpoint' => 'https://metadata.example.com', 'url' => null]) extends ObjectStorageConfiguration
        {
            /** @param array<string, mixed> $values */
            public function __construct(private readonly array $values = [])
            {
                parent::__construct();
            }

            public function getAttribute($key): mixed
            {
                return $this->values[$key] ?? null;
            }
        };
        $policy = new S3EndpointSecurityPolicy(new FakeHostnameResolver([
            'metadata.example.com' => ['169.254.169.254'],
        ]));
        $filesystems = Mockery::mock(FilesystemManager::class);
        $filesystems->shouldNotReceive('build');
        $validator = new S3ObjectStorageConnectionValidator(
            $filesystems,
            new S3FilesystemConfigurationFactory($policy),
        );

        $result = $validator->validate($configuration);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('unsafe_endpoint', $result->failureCode);
    }

    private function expectUnsafeEndpoint(S3EndpointSecurityPolicy $policy, string $url): void
    {
        try {
            $policy->assertUrlIsSafe($url);
            $this->fail("Expected {$url} to be rejected.");
        } catch (UnsafeObjectStorageEndpointException) {
            $this->addToAssertionCount(1);
        }
    }
}

final class FakeHostnameResolver implements HostnameResolver
{
    /** @var list<string> */
    public array $resolvedHostnames = [];

    /** @param array<string, list<string>> $answers */
    public function __construct(private readonly array $answers) {}

    public function resolve(string $hostname): array
    {
        $this->resolvedHostnames[] = $hostname;

        return $this->answers[$hostname] ?? [];
    }
}
