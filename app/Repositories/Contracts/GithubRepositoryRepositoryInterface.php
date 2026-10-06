<?php

namespace App\Repositories\Contracts;

use App\DataTransferObjects\GitHubRepositoryData;
use App\Models\GithubRepository;
use App\Models\SyncTarget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GithubRepositoryRepositoryInterface
{
    /**
     * Paginate the repositories belonging to the given target, applying the
     * requested search/filter/sort options.
     *
     * Supported filters: "search" (matches name/description), "language",
     * "sort" (one of stargazers_count|open_issues_count|github_updated_at),
     * "direction" (asc|desc).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, GithubRepository>
     */
    public function paginateForTarget(SyncTarget $target, array $filters): LengthAwarePaginator;

    /**
     * Create or update the repositories for the given target from the
     * provided GitHub data, keyed by (sync_target_id, github_id).
     *
     * @param  list<GitHubRepositoryData>  $githubRepositoryDataList
     */
    public function upsertMany(SyncTarget $target, array $githubRepositoryDataList): void;
}
