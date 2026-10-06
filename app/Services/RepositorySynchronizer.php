<?php

namespace App\Services;

use App\Models\SyncTarget;
use App\Repositories\Contracts\GithubRepositoryRepositoryInterface;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use App\Services\GitHub\Exceptions\GitHubApiException;
use App\Services\GitHub\GitHubClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepositorySynchronizer
{
    public function __construct(
        private readonly GitHubClient $client,
        private readonly SyncTargetRepositoryInterface $syncTargets,
        private readonly GithubRepositoryRepositoryInterface $repositories,
    ) {}

    /**
     * Synchronize the repositories for a single, globally shared sync
     * target. The GitHub HTTP call happens outside of any transaction;
     * only persisting the fetched repositories and marking the target as
     * synced are wrapped together, so the two always succeed or fail as a
     * unit.
     */
    public function sync(SyncTarget $target): void
    {
        try {
            $repositoryDataList = $this->client->getRepositoriesFor($target);
        } catch (GitHubApiException $exception) {
            $this->fail($target, $exception);

            return;
        }

        DB::transaction(function () use ($target, $repositoryDataList) {
            $this->repositories->upsertMany($target, $repositoryDataList);
            $this->syncTargets->markAsSynced($target, now());
        });
    }

    private function fail(SyncTarget $target, GitHubApiException $exception): void
    {
        Log::error('GitHub repository sync failed.', [
            'sync_target_id' => $target->id,
            'sync_target_name' => $target->name,
            'sync_target_type' => $target->type->value,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        $this->syncTargets->markAsFailed($target, $exception->getMessage());
    }
}
