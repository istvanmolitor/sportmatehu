<?php

use App\Enums\SyncStatus;
use App\Enums\SyncTargetType;
use App\Models\SyncTarget;
use App\Services\RepositorySynchronizer;
use Illuminate\Support\Facades\Http;

test('sync stores the fetched repositories and marks the target as synced', function () {
    Http::fake([
        'https://api.github.com/orgs/laravel/repos*' => Http::response([
            fakeGitHubRepo(1, 'framework'),
            fakeGitHubRepo(2, 'cashier'),
        ], 200),
    ]);

    $target = SyncTarget::factory()->create(['name' => 'laravel', 'type' => SyncTargetType::Organization]);

    app(RepositorySynchronizer::class)->sync($target);

    $target->refresh();
    expect($target->status)->toBe(SyncStatus::Success);
    expect($target->last_synced_at)->not->toBeNull();
    expect($target->last_sync_error)->toBeNull();
    expect($target->repositories()->count())->toBe(2);
    expect($target->repositories()->pluck('full_name')->all())
        ->toBe(['octocat/framework', 'octocat/cashier']);
});

test('syncing the same target twice does not duplicate repositories, it updates them', function () {
    Http::fake([
        'https://api.github.com/orgs/laravel/repos*' => Http::sequence()
            ->push([fakeGitHubRepo(1, 'framework', 100)], 200)
            ->push([fakeGitHubRepo(1, 'framework', 250)], 200),
    ]);

    $target = SyncTarget::factory()->create(['name' => 'laravel', 'type' => SyncTargetType::Organization]);

    app(RepositorySynchronizer::class)->sync($target);
    app(RepositorySynchronizer::class)->sync($target);

    expect($target->repositories()->count())->toBe(1);
    expect($target->repositories()->first()->stargazers_count)->toBe(250);
});

test('sync marks the target as failed with a human-readable message when GitHub returns a 404', function () {
    Http::fake([
        'https://api.github.com/users/missing-user/repos*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $target = SyncTarget::factory()->create(['name' => 'missing-user', 'type' => SyncTargetType::User]);

    app(RepositorySynchronizer::class)->sync($target);

    $target->refresh();
    expect($target->status)->toBe(SyncStatus::Failed);
    expect($target->last_sync_error)->not->toBeNull();
    expect($target->repositories()->count())->toBe(0);
});
