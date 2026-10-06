<?php

namespace App\Providers;

use App\Repositories\Contracts\GithubRepositoryRepositoryInterface;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use App\Repositories\EloquentGithubRepositoryRepository;
use App\Repositories\EloquentSyncTargetRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SyncTargetRepositoryInterface::class, EloquentSyncTargetRepository::class);
        $this->app->bind(GithubRepositoryRepositoryInterface::class, EloquentGithubRepositoryRepository::class);
    }
}
