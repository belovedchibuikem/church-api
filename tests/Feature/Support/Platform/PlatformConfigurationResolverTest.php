<?php

namespace Tests\Feature\Support\Platform;

use App\Models\AuditEvent;
use App\Models\User;
use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use App\Support\Authorization\ScopeReference;
use App\Support\Platform\PlatformConfigurationResolver;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use App\Support\Platform\UpsertPlatformConfigurationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PlatformConfigurationResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_resolves_environment_and_exact_scope_overrides_in_precedence_order(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.testing.precedence');
        $churchScope = new ScopeReference('church', '01JCHURCH00000000000000000');
        $action = $this->app->make(UpsertPlatformConfigurationAction::class);

        $this->putValue($action, $actor, $key, new PlatformContext('*'), 'global-default');
        $this->putValue($action, $actor, $key, new PlatformContext('production'), 'production-default');
        $this->putValue($action, $actor, $key, new PlatformContext('*', $churchScope), 'global-scope');
        $this->putValue($action, $actor, $key, new PlatformContext('production', $churchScope), 'production-scope');

        $resolver = $this->app->make(PlatformConfigurationResolver::class);
        $this->assertSame(
            'production-scope',
            $resolver->resolve($key, new PlatformContext('production', $churchScope)),
        );
        $this->assertSame(
            'global-scope',
            $resolver->resolve($key, new PlatformContext('staging', $churchScope)),
        );
        $this->assertSame(
            'production-default',
            $resolver->resolve($key, new PlatformContext('production')),
        );
        $this->assertSame(
            'global-default',
            $resolver->resolve($key, new PlatformContext('staging')),
        );
    }

    public function test_update_invalidates_a_previously_cached_effective_value(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.testing.cache');
        $context = new PlatformContext('testing');
        $action = $this->app->make(UpsertPlatformConfigurationAction::class);
        $this->putValue($action, $actor, $key, $context, 'before');
        $resolver = $this->app->make(PlatformConfigurationResolver::class);
        $this->assertSame('before', $resolver->resolve($key, $context));

        $this->putValue($action, $actor, $key, $context, 'after');

        $this->assertSame('after', $resolver->resolve($key, $context));
        $this->assertSame(
            ['platform.configuration.created', 'platform.configuration.updated'],
            AuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    private function putValue(
        UpsertPlatformConfigurationAction $action,
        User $actor,
        PlatformKey $key,
        PlatformContext $context,
        string $value,
    ): void {
        $action->handle(
            $key,
            ConfigurationValueType::String,
            ConfigurationClassification::Internal,
            $value,
            $context,
            $actor,
        );
    }
}
