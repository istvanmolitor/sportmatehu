<?php

namespace App\Policies;

use App\Models\SyncTarget;
use App\Models\User;

class SyncTargetPolicy
{
    /**
     * Determine whether the user may view the given sync target (and its
     * repositories, and trigger a sync for it) - i.e. whether the user has
     * added it to their own list.
     */
    public function view(User $user, SyncTarget $target): bool
    {
        return $user->syncTargets()->where('sync_target_id', $target->id)->exists();
    }
}
