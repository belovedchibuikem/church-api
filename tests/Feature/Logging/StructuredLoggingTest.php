<?php

namespace Tests\Feature\Logging;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use JsonException;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Tests\TestCase;

class StructuredLoggingTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_json_logs_include_correlation_context_and_redact_sensitive_values(): void
    {
        $correlationId = '0e984725-c51c-4bf4-9960-e1c80e27aba0';
        $logStream = fopen('php://memory', 'w+');
        $this->assertIsResource($logStream);

        $channel = config('logging.channels.single');
        $this->assertIsArray($channel);

        $channel['driver'] = 'monolog';
        $channel['handler'] = StreamHandler::class;
        $channel['handler_with'] = ['stream' => $logStream];
        $channel['processors'] = [PsrLogMessageProcessor::class];
        unset($channel['path'], $channel['replace_placeholders']);

        config()->set('logging.channels.structured_test', $channel);

        Context::add('correlation_id', $correlationId);

        try {
            Log::channel('structured_test')->warning(
                'Login failed for {email} using {password}',
                [
                    'email' => 'person@example.test',
                    'password' => 'unsafe-password',
                    'operation' => 'identity.login',
                    'nested' => [
                        'access_token' => 'unsafe-token',
                        'reason' => 'invalid-credentials',
                    ],
                ],
            );

            rewind($logStream);
            $rawLog = stream_get_contents($logStream);
            $this->assertIsString($rawLog);

            $entry = json_decode(trim($rawLog), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(
                'Login failed for [REDACTED] using [REDACTED]',
                $entry['message'],
            );
            $this->assertSame('WARNING', $entry['level_name']);
            $this->assertSame($correlationId, $entry['extra']['correlation_id']);
            $this->assertSame('identity.login', $entry['context']['operation']);
            $this->assertSame('[REDACTED]', $entry['context']['email']);
            $this->assertSame('[REDACTED]', $entry['context']['password']);
            $this->assertSame('[REDACTED]', $entry['context']['nested']['access_token']);
            $this->assertSame('invalid-credentials', $entry['context']['nested']['reason']);
            $this->assertStringNotContainsString('person@example.test', $rawLog);
            $this->assertStringNotContainsString('unsafe-password', $rawLog);
            $this->assertStringNotContainsString('unsafe-token', $rawLog);
        } finally {
            Log::forgetChannel('structured_test');
            Context::flush();
            fclose($logStream);
        }
    }
}
