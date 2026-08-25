<?php

namespace App\Support\Health;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Str;
use Throwable;

class LaravelReadinessChecker implements ReadinessChecker
{
    public function __construct(
        private DatabaseManager $database,
        private CacheManager $cache,
        private QueueManager $queue,
    ) {}

    public function check(): ReadinessResult
    {
        $checks = [
            'database' => $this->status(fn (): bool => $this->databaseIsAvailable()),
            'cache' => $this->status(fn (): bool => $this->cacheIsAvailable()),
            'queue' => $this->status(fn (): bool => $this->queueIsAvailable()),
        ];

        return new ReadinessResult(
            ready: ! in_array('failed', $checks, true),
            checks: $checks,
        );
    }

    private function databaseIsAvailable(): bool
    {
        return $this->database->connection()->getPdo() !== null;
    }

    private function cacheIsAvailable(): bool
    {
        $key = 'readiness:'.Str::uuid();
        $cache = $this->cache->store();

        try {
            if (! $cache->put($key, 'ready', 10)) {
                return false;
            }

            return $cache->get($key) === 'ready';
        } finally {
            $cache->forget($key);
        }
    }

    private function queueIsAvailable(): bool
    {
        $connectionName = config('queue.default');
        $connectionConfiguration = config("queue.connections.{$connectionName}");

        if (! is_string($connectionName) || ! is_array($connectionConfiguration)) {
            return false;
        }

        if (($connectionConfiguration['driver'] ?? null) === 'database') {
            $databaseConnection = $connectionConfiguration['connection'] ?? null;
            $table = $connectionConfiguration['table'] ?? 'jobs';

            return is_string($table)
                && $this->database->connection($databaseConnection)->getSchemaBuilder()->hasTable($table);
        }

        return $this->queue->connection($connectionName) !== null;
    }

    private function status(callable $check): string
    {
        try {
            return $check() ? 'ok' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
