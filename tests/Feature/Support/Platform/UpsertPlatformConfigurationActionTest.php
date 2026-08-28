<?php

namespace Tests\Feature\Support\Platform;

use App\Models\AuditEvent;
use App\Models\PlatformConfiguration;
use App\Models\User;
use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Platform\PlatformConfigurationResolver;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use App\Support\Platform\UpsertPlatformConfigurationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class UpsertPlatformConfigurationActionTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('typedValues')]
    public function test_persists_and_resolves_typed_internal_values(
        ConfigurationValueType $type,
        mixed $value,
    ): void {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.testing.typed_value');
        $context = new PlatformContext('testing');

        $configuration = $this->app->make(UpsertPlatformConfigurationAction::class)->handle(
            $key,
            $type,
            ConfigurationClassification::Internal,
            $value,
            $context,
            $actor,
        );

        $this->assertModelExists($configuration);
        $this->assertSame($type, $configuration->value_type);
        $this->assertSame($actor->getKey(), $configuration->updated_by_user_id);
        $this->assertSame(
            $value,
            $this->app->make(PlatformConfigurationResolver::class)->resolve($key, $context),
        );
        $this->assertSame('platform.configuration.created', AuditEvent::query()->sole()->action);
    }

    #[DataProvider('typeMismatches')]
    public function test_rejects_type_mismatches_without_writing_records(
        ConfigurationValueType $type,
        mixed $value,
    ): void {
        $actor = User::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(UpsertPlatformConfigurationAction::class)->handle(
                new PlatformKey('platform.testing.invalid_value'),
                $type,
                ConfigurationClassification::Internal,
                $value,
                new PlatformContext('testing'),
                $actor,
            );
            $this->fail('Expected the configuration type mismatch to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, PlatformConfiguration::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_encrypts_and_hides_confidential_values_from_serialization_and_audit(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.testing.confidential_value');
        $context = new PlatformContext('production');
        $secret = 'confidential-test-value';

        $configuration = $this->app->make(UpsertPlatformConfigurationAction::class)->handle(
            $key,
            ConfigurationValueType::String,
            ConfigurationClassification::Confidential,
            $secret,
            $context,
            $actor,
        );

        $storedValue = $configuration->getRawOriginal('stored_value');
        $this->assertNotSame($secret, $storedValue);
        $this->assertStringNotContainsString($secret, $storedValue);
        $this->assertArrayNotHasKey('stored_value', $configuration->toArray());
        $this->assertSame(
            $secret,
            $this->app->make(PlatformConfigurationResolver::class)->resolve($key, $context),
        );

        $auditEvent = AuditEvent::query()->sole();
        $this->assertStringNotContainsString($secret, json_encode($auditEvent->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_rolls_back_configuration_when_audit_recording_fails(): void
    {
        $actor = User::factory()->create();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Audit unavailable'));
        $wasRolledBack = false;

        try {
            $this->app->make(UpsertPlatformConfigurationAction::class)->handle(
                new PlatformKey('platform.testing.rollback'),
                ConfigurationValueType::Boolean,
                ConfigurationClassification::Internal,
                true,
                new PlatformContext('testing'),
                $actor,
            );
            $this->fail('Expected the failed audit to roll back the configuration.');
        } catch (RuntimeException) {
            $wasRolledBack = true;
        }

        $this->assertTrue($wasRolledBack);
        $this->assertSame(0, PlatformConfiguration::query()->count());
    }

    /**
     * @return array<string, array{ConfigurationValueType, mixed}>
     */
    public static function typedValues(): array
    {
        return [
            'string' => [ConfigurationValueType::String, 'maintenance'],
            'integer' => [ConfigurationValueType::Integer, 15],
            'boolean' => [ConfigurationValueType::Boolean, true],
            'json' => [ConfigurationValueType::Json, ['channels' => ['email', 'in_app']]],
        ];
    }

    /**
     * @return array<string, array{ConfigurationValueType, mixed}>
     */
    public static function typeMismatches(): array
    {
        return [
            'string receives integer' => [ConfigurationValueType::String, 1],
            'integer receives numeric string' => [ConfigurationValueType::Integer, '1'],
            'boolean receives integer' => [ConfigurationValueType::Boolean, 1],
            'json receives object' => [ConfigurationValueType::Json, new \stdClass],
        ];
    }
}
