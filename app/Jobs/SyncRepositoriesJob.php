<?php

namespace App\Jobs;

use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use App\Services\RepositorySynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
