<?php

namespace App\Jobs;

use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use App\Services\RepositorySynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Synchronizes a single sync target's repositories in the background.
 *
 * The target is put into the "syncing" state by
 * SyncTargetRepositoryInterface::markAsSyncing() in the controller, in the
 * same atomic step that decides whether this job gets dispatched at all
 * (see SyncTargetSyncController) - that is what prevents two concurrent
 * requests from both starting a sync for the same target. By the time this
 * job runs, the target is already "syncing"; RepositorySynchronizer is
 * responsible for leaving it in "success" or "failed" afterwards.
 *
 * Notes on topics intentionally left at the comment level for this baseline
 * (see PLAN.md section 5):
 * - Overlap protection at the queue level: a WithoutOverlapping middleware
 *   keyed by the target id would be a good defense-in-depth addition
 *   alongside the controller-level guard above.
 * - Retries/backoff: $tries/backoff() would let transient GitHub errors
 *   (timeouts, 5xx) retry a few times before giving up; a failed() hook
 *   could ensure the target is still marked "failed" if retries are
 *   exhausted by an exception that escapes RepositorySynchronizer.
 * - Timeout: a $timeout property bounds how long a worker may run this job.
 */
class SyncRepositoriesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $syncTargetId,
    ) {}

    public function handle(SyncTargetRepositoryInterface $syncTargets, RepositorySynchronizer $synchronizer): void
    {
        $target = $syncTargets->find($this->syncTargetId);

        if ($target === null) {
            return;
        }

        $synchronizer->sync($target);
    }
}
