<?php

use App\Enums\SyncStatus;
use App\Enums\SyncTargetType;
use App\Jobs\SyncRepositoriesJob;
use App\Models\SyncTarget;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;
use App\Services\RepositorySynchronizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('it can be dispatched to the queue for a given sync target', function () {
    Queue::fake();
    $target = SyncTarget::factory()->create();

    SyncRepositoriesJob::dispatch($target->id);

    Queue::assertPushed(
        SyncRepositoriesJob::class,
        fn (SyncRepositoriesJob $job) => $job->syncTargetId === $target->id,
    );
});

test('handling the job fetches and stores repositories and marks the target as synced', function () {
    Http::fake([
        'https://api.github.com/orgs/laravel/repos*' => Http::response([
            fakeGitHubRepo(1, 'framework'),
        ], 200),
    ]);

    $target = SyncTarget::factory()->create(['name' => 'laravel', 'type' => SyncTargetType::Organization]);

    app(SyncRepositoriesJob::class, ['syncTargetId' => $target->id])->handle(
        app(SyncTargetRepositoryInterface::class),
        app(RepositorySynchronizer::class),
    );

    $target->refresh();
    expect($target->status)->toBe(SyncStatus::Success);
    expect($target->repositories()->count())->toBe(1);
});

test('handling the job marks the target as failed when the GitHub call fails', function () {
    Http::fake([
        'https://api.github.com/users/missing/repos*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $target = SyncTarget::factory()->create(['name' => 'missing', 'type' => SyncTargetType::User]);

    app(SyncRepositoriesJob::class, ['syncTargetId' => $target->id])->handle(
        app(SyncTargetRepositoryInterface::class),
        app(RepositorySynchronizer::class),
    );

    $target->refresh();
    expect($target->status)->toBe(SyncStatus::Failed);
    expect($target->last_sync_error)->not->toBeNull();
});

test('handling the job is a no-op when the sync target no longer exists', function () {
    app(SyncRepositoriesJob::class, ['syncTargetId' => 999_999])->handle(
        app(SyncTargetRepositoryInterface::class),
        app(RepositorySynchronizer::class),
    );
})->throwsNoExceptions();
