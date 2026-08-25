<?php

use Illuminate\Support\Facades\Schedule;

$failedJobRetentionHours = max(1, (int) config('queue.retention.failed_hours'));
$batchRetentionHours = max(1, (int) config('queue.retention.batch_hours'));
$unfinishedBatchRetentionHours = max(1, (int) config('queue.retention.unfinished_batch_hours'));
$cancelledBatchRetentionHours = max(1, (int) config('queue.retention.cancelled_batch_hours'));

Schedule::command("queue:prune-failed --hours={$failedJobRetentionHours}")
    ->name('operations.queue.prune_failed')
    ->dailyAt('01:00')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command(
    "queue:prune-batches --hours={$batchRetentionHours} "
    ."--unfinished={$unfinishedBatchRetentionHours} "
    ."--cancelled={$cancelledBatchRetentionHours}",
)
    ->name('operations.queue.prune_batches')
    ->dailyAt('01:15')
    ->withoutOverlapping(60)
    ->onOneServer();
