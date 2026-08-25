<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

class ConfigurePrivacySafeLogging
{
    public function __construct(private RedactSensitiveLogContext $redactSensitiveLogContext) {}

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor($this->redactSensitiveLogContext);
        }
    }
}
