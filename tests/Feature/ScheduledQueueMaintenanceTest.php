<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ScheduledQueueMaintenanceTest extends TestCase
{
    public function test_registers_protected_queue_retention_tasks(): void
    {
        $scheduledEvents = $this->scheduledEventsByName();

        $failedJobPruning = $scheduledEvents->get('operations.queue.prune_failed');
        $batchPruning = $scheduledEvents->get('operations.queue.prune_batches');

        $this->assertInstanceOf(Event::class, $failedJobPruning);
        $this->assertSame('0 1 * * *', $failedJobPruning->getExpression());
        $this->assertStringContainsString('queue:prune-failed', $failedJobPruning->command);
        $this->assertStringContainsString('--hours=168', $failedJobPruning->command);
        $this->assertTrue($failedJobPruning->withoutOverlapping);
        $this->assertTrue($failedJobPruning->onOneServer);
        $this->assertSame(60, $failedJobPruning->expiresAt);

        $this->assertInstanceOf(Event::class, $batchPruning);
        $this->assertSame('15 1 * * *', $batchPruning->getExpression());
        $this->assertStringContainsString('queue:prune-batches', $batchPruning->command);
        $this->assertStringContainsString('--hours=48', $batchPruning->command);
        $this->assertStringContainsString('--unfinished=168', $batchPruning->command);
        $this->assertStringContainsString('--cancelled=168', $batchPruning->command);
        $this->assertTrue($batchPruning->withoutOverlapping);
        $this->assertTrue($batchPruning->onOneServer);
        $this->assertSame(60, $batchPruning->expiresAt);
    }

    /**
     * @return Collection<string, Event>
     */
    private function scheduledEventsByName(): Collection
    {
        return collect($this->app->make(Schedule::class)->events())
            ->keyBy(fn (Event $event): ?string => $event->description);
    }
}
