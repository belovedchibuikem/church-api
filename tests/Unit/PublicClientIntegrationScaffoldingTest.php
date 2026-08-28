<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PublicClientIntegrationScaffoldingTest extends TestCase
{
    public function test_public_client_integration_scaffolding_is_deterministic_and_public_only(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([PHP_BINARY, 'scripts/verify-public-client-integrations.php'], $root);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringContainsString(
            'Public client integration scaffolding is valid.',
            $process->getOutput(),
        );
    }
}
