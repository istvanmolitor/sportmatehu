<?php

namespace App\Models;

use Database\Factories\GithubRepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sync_target_id
 * @property int $github_id
 * @property string $name
 * @property string $full_name
 * @property string|null $description
 * @property string $url
 * @property string|null $language
 * @property int $stargazers_count
 * @property int $open_issues_count
 * @property bool $is_archived
 * @property Carbon|null $github_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'sync_target_id',
    'github_id',
    'name',
    'full_name',
    'description',
    'url',
    'language',
    'stargazers_count',
    'open_issues_count',
    'is_archived',
    'github_updated_at',
])]
class GithubRepository extends Model
{
    /** @use HasFactory<GithubRepositoryFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'repositories';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'github_updated_at' => 'datetime',
        ];
    }

    /**
     * The sync target this repository belongs to.
     *
     * @return BelongsTo<SyncTarget, $this>
     */
    public function syncTarget(): BelongsTo
    {
        return $this->belongsTo(SyncTarget::class);
    }
}
