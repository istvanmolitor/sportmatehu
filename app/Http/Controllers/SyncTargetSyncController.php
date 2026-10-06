<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRepositoriesJob;
use App\Models\SyncTarget;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SyncTargetSyncController extends Controller
{
    public function __construct(
        private readonly SyncTargetRepositoryInterface $syncTargets,
    ) {}

    /**
     * Start a background sync for the given target.
     *
     * The atomic markAsSyncing() call is both the duplicate-request guard
     * and the state transition: if it returns false the target was already
     * syncing, so no second job is dispatched.
     */
    public function store(SyncTarget $syncTarget): RedirectResponse
    {
        $this->authorize('view', $syncTarget);

        if (! $this->syncTargets->markAsSyncing($syncTarget)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This target is already syncing.')]);

            return back();
        }

        SyncRepositoriesJob::dispatch($syncTarget->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sync started.')]);

        return back();
    }
}
