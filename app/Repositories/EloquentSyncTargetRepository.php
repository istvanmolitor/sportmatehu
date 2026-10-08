<?php

namespace App\Repositories;

use App\Enums\SyncStatus;
use App\Enums\SyncTargetType;
use App\Models\SyncTarget;
use App\Models\User;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentSyncTargetRepository implements SyncTargetRepositoryInterface
{
    public function allForUser(User $user): Collection
    {
        return $user->syncTargets()->get();
    }

    public function find(int $id): ?SyncTarget
    {
        return SyncTarget::query()->find($id);
    }

    public function findOrCreateAndAttachToUser(User $user, string $name, SyncTargetType $type): SyncTarget
    {
        $target = $this->firstOrCreateTarget($name, $type);

        $this->attachToUserIfMissing($user, $target);

        return $target;
    }

    public function userHasTarget(User $user, string $name, SyncTargetType $type): bool
    {
        return $user->syncTargets()
            ->where('name', $name)
            ->where('type', $type)
            ->exists();
    }

    public function markAsSyncing(SyncTarget $target): bool
    {
        $updated = SyncTarget::query()
            ->where('id', $target->id)
            ->where('status', '!=', SyncStatus::Syncing)
            ->update(['status' => SyncStatus::Syncing]);

        return $updated > 0;
    }

    public function markAsSynced(SyncTarget $target, CarbonInterface $at): void
    {
        $target->forceFill([
            'status' => SyncStatus::Success,
            'last_synced_at' => $at,
            'last_sync_error' => null,
        ])->save();
    }

    public function markAsFailed(SyncTarget $target, string $error): void
    {
        $target->forceFill([
            'status' => SyncStatus::Failed,
            'last_sync_error' => $error,
        ])->save();
    }

    /**
     * Find the globally unique sync target by (name, type), creating it if
     * necessary. Two concurrent requests may both find no existing row and
     * attempt to create one; the unique (name, type) constraint guarantees
     * only one insert succeeds, and the loser simply re-reads the row the
     * winner just created.
     */
    private function firstOrCreateTarget(string $name, SyncTargetType $type): SyncTarget
    {
        try {
            return DB::transaction(fn () => SyncTarget::query()->firstOrCreate(
                ['name' => $name, 'type' => $type],
                ['status' => SyncStatus::Pending],
            ));
        } catch (UniqueConstraintViolationException) {
            return SyncTarget::query()->where('name', $name)->where('type', $type)->firstOrFail();
        }
    }

    /**
     * Attach the pivot row for this user/target if it does not already
     * exist. The unique (user_id, sync_target_id) constraint protects
     * against the same race between two concurrent requests from the same
     * user.
     */
    private function attachToUserIfMissing(User $user, SyncTarget $target): void
    {
        if ($user->syncTargets()->where('sync_target_id', $target->id)->exists()) {
            return;
        }

        try {
            $user->syncTargets()->attach($target->id);
        } catch (UniqueConstraintViolationException) {
            // Already attached by a concurrent request - nothing to do.
        }
    }
}
