<?php

use App\DataTransferObjects\GitHubRepositoryData;
use App\Models\SyncTarget;
use App\Repositories\Contracts\GithubRepositoryRepositoryInterface;
use Illuminate\Support\Carbon;

test('upsertMany creates repositories and running it again updates instead of duplicating', function () {
    $repository = app(GithubRepositoryRepositoryInterface::class);
    $target = SyncTarget::factory()->create();

    $data = [new GitHubRepositoryData(
        githubId: 123,
        name: 'repo',
        fullName: 'owner/repo',
        description: 'first description',
        url: 'https://github.com/owner/repo',
        language: 'PHP',
        stargazersCount: 10,
        openIssuesCount: 2,
        isArchived: false,
        githubUpdatedAt: Carbon::parse('2026-01-01'),
    )];

    $repository->upsertMany($target, $data);
    expect($target->repositories()->count())->toBe(1);

    $updated = [new GitHubRepositoryData(
        githubId: 123,
        name: 'repo',
        fullName: 'owner/repo',
        description: 'updated description',
        url: 'https://github.com/owner/repo',
        language: 'PHP',
        stargazersCount: 20,
        openIssuesCount: 3,
        isArchived: false,
        githubUpdatedAt: Carbon::parse('2026-02-01'),
    )];

    $repository->upsertMany($target, $updated);

    expect($target->repositories()->count())->toBe(1);
    $repo = $target->repositories()->first();
    expect($repo->description)->toBe('updated description');
    expect($repo->stargazers_count)->toBe(20);
});

test('paginateForTarget filters by search and sorts by the requested column', function () {
    $repository = app(GithubRepositoryRepositoryInterface::class);
    $target = SyncTarget::factory()->create();

    $target->repositories()->create((new GitHubRepositoryData(
        githubId: 1, name: 'alpha', fullName: 'o/alpha', description: 'first repo',
        url: 'https://github.com/o/alpha', language: 'PHP', stargazersCount: 5,
        openIssuesCount: 1, isArchived: false, githubUpdatedAt: now(),
    ))->toAttributes());

    $target->repositories()->create((new GitHubRepositoryData(
        githubId: 2, name: 'beta', fullName: 'o/beta', description: 'second repo',
        url: 'https://github.com/o/beta', language: 'JavaScript', stargazersCount: 50,
        openIssuesCount: 1, isArchived: false, githubUpdatedAt: now(),
    ))->toAttributes());

    $results = $repository->paginateForTarget($target, ['search' => 'alpha']);
    expect($results->total())->toBe(1);
    expect($results->first()->name)->toBe('alpha');

    $sorted = $repository->paginateForTarget($target, ['sort' => 'stargazers_count', 'direction' => 'desc']);
    expect($sorted->first()->name)->toBe('beta');
});
