<?php

use App\Enums\SyncTargetType;
use App\Models\SyncTarget;
use App\Services\GitHub\Exceptions\GitHubApiException;
use App\Services\GitHub\Exceptions\GitHubRateLimitException;
use App\Services\GitHub\Exceptions\GitHubTargetNotFoundException;
use App\Services\GitHub\GitHubClient;
use Illuminate\Support\Facades\Http;

test('it calls the users endpoint for a user target', function () {
    Http::fake([
        'https://api.github.com/users/octocat/repos*' => Http::response([fakeGitHubRepo(1, 'repo-a')], 200),
    ]);

    $target = SyncTarget::factory()->make(['name' => 'octocat', 'type' => SyncTargetType::User]);
    $repositories = app(GitHubClient::class)->getRepositoriesFor($target);

    expect($repositories)->toHaveCount(1);
    expect($repositories[0]->fullName)->toBe('octocat/repo-a');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/octocat/repos'));
});

test('it calls the orgs endpoint for an organization target', function () {
    Http::fake([
        'https://api.github.com/orgs/laravel/repos*' => Http::response([fakeGitHubRepo(2, 'framework')], 200),
    ]);

    $target = SyncTarget::factory()->make(['name' => 'laravel', 'type' => SyncTargetType::Organization]);
    $repositories = app(GitHubClient::class)->getRepositoriesFor($target);

    expect($repositories)->toHaveCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/orgs/laravel/repos'));
});

test('it follows pagination via the Link header until there is no next page', function () {
    Http::fake([
        'https://api.github.com/users/octocat/repos?per_page=100' => Http::response(
            [fakeGitHubRepo(1, 'repo-a')],
            200,
            ['Link' => '<https://api.github.com/users/octocat/repos?per_page=100&page=2>; rel="next"'],
        ),
        'https://api.github.com/users/octocat/repos?per_page=100&page=2' => Http::response(
            [fakeGitHubRepo(2, 'repo-b')],
            200,
        ),
    ]);

    $target = SyncTarget::factory()->make(['name' => 'octocat', 'type' => SyncTargetType::User]);
    $repositories = app(GitHubClient::class)->getRepositoriesFor($target);

    expect($repositories)->toHaveCount(2);
    expect(collect($repositories)->pluck('name')->all())->toBe(['repo-a', 'repo-b']);
});

test('it throws a target-not-found exception on a 404 response', function () {
    Http::fake(['*' => Http::response(['message' => 'Not Found'], 404)]);

    $target = SyncTarget::factory()->make(['name' => 'missing', 'type' => SyncTargetType::User]);

    expect(fn () => app(GitHubClient::class)->getRepositoriesFor($target))
        ->toThrow(GitHubTargetNotFoundException::class);
});

test('it throws a rate limit exception on a 403 response with no remaining requests', function () {
    Http::fake(['*' => Http::response(
        ['message' => 'rate limit exceeded'],
        403,
        ['X-RateLimit-Remaining' => '0', 'Retry-After' => '30'],
    )]);

    $target = SyncTarget::factory()->make(['name' => 'octocat', 'type' => SyncTargetType::User]);

    try {
        app(GitHubClient::class)->getRepositoriesFor($target);
        test()->fail('Expected GitHubRateLimitException to be thrown.');
    } catch (GitHubRateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(30);
    }
});

test('it throws a generic api exception on a server error', function () {
    Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

    $target = SyncTarget::factory()->make(['name' => 'octocat', 'type' => SyncTargetType::User]);

    expect(fn () => app(GitHubClient::class)->getRepositoriesFor($target))
        ->toThrow(GitHubApiException::class);
});
