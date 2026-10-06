<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Enums\SyncTargetType;
use Database\Factories\SyncTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property SyncTargetType $type
 * @property SyncStatus $status
 * @property Carbon|null $last_synced_at
 * @property string|null $last_sync_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'type'])]
class SyncTarget extends Model
{
    /** @use HasFactory<SyncTargetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SyncTargetType::class,
            'status' => SyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * The Laravel users that follow this sync target.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sync_target_user');
    }

    /**
     * The repositories synchronized for this target.
     *
     * @return HasMany<GithubRepository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(GithubRepository::class);
    }
}
