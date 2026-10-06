<?php

namespace App\Repositories;

use App\DataTransferObjects\GitHubRepositoryData;
use App\Models\GithubRepository;
use App\Models\SyncTarget;
use App\Repositories\Contracts\GithubRepositoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentGithubRepositoryRepository implements GithubRepositoryRepositoryInterface
{
    /**
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = ['stargazers_count', 'open_issues_count', 'github_updated_at'];

    /**
     * @return LengthAwarePaginator<int, GithubRepository>
     */
    public function paginateForTarget(SyncTarget $target, array $filters): LengthAwarePaginator
    {
        $query = $target->repositories();

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (filled($filters['language'] ?? null)) {
            $query->where('language', $filters['language']);
        }

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE_COLUMNS, true)
            ? $filters['sort']
            : 'github_updated_at';

        $direction = ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }

    public function upsertMany(SyncTarget $target, array $githubRepositoryDataList): void
    {
        if ($githubRepositoryDataList === []) {
            return;
        }

        $now = now();

        $rows = array_map(
            fn (GitHubRepositoryData $data) => [
                'sync_target_id' => $target->id,
                ...$data->toAttributes(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $githubRepositoryDataList,
        );

        GithubRepository::query()->upsert(
            $rows,
            ['sync_target_id', 'github_id'],
            [
                'name',
                'full_name',
                'description',
                'url',
                'language',
                'stargazers_count',
                'open_issues_count',
                'is_archived',
                'github_updated_at',
                'updated_at',
            ],
        );
    }
}
