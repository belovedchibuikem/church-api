<?php

namespace Tests\Unit;

use App\Support\Security\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_generates_the_rfc_6238_sha1_code_for_a_known_counter(): void
    {
        $service = new TotpService;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $code = $service->codeAtCounter($secret, 1);

        $this->assertSame('287082', $code);
        $this->assertSame(1, $service->matchingCounter($secret, '287082', 59, 0));
        $this->assertNull($service->matchingCounter($secret, '287083', 59, 0));
    }

    public function test_generated_secret_and_provisioning_uri_are_authenticator_compatible(): void
    {
        $service = new TotpService;

        $secret = $service->generateSecret();
        $uri = $service->provisioningUri($secret, 'member@example.com');

        $this->assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
        $this->assertStringContainsString('digits=6&period=30', $uri);
    }
}
