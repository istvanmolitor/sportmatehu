<?php

namespace App\Repositories\Contracts;

use App\Enums\SyncTargetType;
use App\Models\SyncTarget;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface SyncTargetRepositoryInterface
{
    /**
     * List the sync targets the given user follows.
     *
     * @return Collection<int, SyncTarget>
     */
    public function allForUser(User $user): Collection;

    /**
     * Find a sync target by id, or null if it does not exist.
     */
    public function find(int $id): ?SyncTarget;

    /**
     * Find the global sync target by (name, type), creating it if it does not
     * exist yet, and attach it to the given user if not already attached.
     */
    public function findOrCreateAndAttachToUser(User $user, string $name, SyncTargetType $type): SyncTarget;

    /**
     * Atomically mark the target as syncing, unless it already is.
     *
     * Returns true if this call transitioned the target into the syncing
     * state, false if it was already syncing (and therefore no job should be
     * dispatched by the caller).
     */
    public function markAsSyncing(SyncTarget $target): bool;

    /**
     * Mark the target as successfully synced at the given time.
     */
    public function markAsSynced(SyncTarget $target, CarbonInterface $at): void;

    /**
     * Mark the target as failed with a human-readable error message.
     */
    public function markAsFailed(SyncTarget $target, string $error): void;
}
