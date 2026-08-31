<?php

namespace Tests\Unit;

use App\Support\Security\MobileCredentialTtl;
use Tests\TestCase;

class MobileCredentialTtlTest extends TestCase
{
    public function test_session_and_access_last_at_least_thirty_days(): void
    {
        config([
            'api.mobile.access_ttl_seconds' => 3600,
            'api.mobile.refresh_ttl_seconds' => 3600,
        ]);

        $this->assertSame(2_592_000, MobileCredentialTtl::sessionSeconds());
        $this->assertSame(2_592_000, MobileCredentialTtl::accessSeconds());
        $this->assertTrue(MobileCredentialTtl::sessionExpiresAt()->greaterThan(now()->addDays(29)));
    }
}
