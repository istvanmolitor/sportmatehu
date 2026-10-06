<?php

namespace App\Services\GitHub;

use App\DataTransferObjects\GitHubRepositoryData;
use App\Enums\SyncTargetType;
use App\Models\SyncTarget;
use App\Services\GitHub\Exceptions\GitHubApiException;
use App\Services\GitHub\Exceptions\GitHubRateLimitException;
use App\Services\GitHub\Exceptions\GitHubTargetNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GitHubClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
    ) {}

    /**
     * Fetch all public repositories for the given sync target, following
     * pagination (via the response "Link" header) until there is no next
     * page left.
     *
     * @return list<GitHubRepositoryData>
     */
    public function getRepositoriesFor(SyncTarget $target): array
    {
        $path = $target->type === SyncTargetType::Organization
            ? "orgs/{$target->name}/repos"
            : "users/{$target->name}/repos";

        $repositories = [];
        $url = "{$this->baseUrl}/{$path}?per_page=100";

        while ($url !== null) {
            $response = $this->fetch($url, $target);

            foreach ((array) $response->json() as $repository) {
                $repositories[] = GitHubRepositoryData::fromGitHubResponse($repository);
            }

            $url = $this->nextPageUrl($response);
        }

        return $repositories;
    }

    private function fetch(string $url, SyncTarget $target): Response
    {
        try {
            $response = $this->request()->get($url);
        } catch (ConnectionException $exception) {
            throw new GitHubApiException(
                "A GitHub API nem válaszolt időben ({$target->name}).",
                previous: $exception,
            );
        }

        $this->ensureSuccessful($response, $target);

        return $response;
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout(10)->acceptJson();

        return $this->token ? $request->withToken($this->token) : $request;
    }

    private function ensureSuccessful(Response $response, SyncTarget $target): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 404) {
            throw new GitHubTargetNotFoundException(
                "GitHub nem talált ilyen nevű {$target->type->value} típusú célt: {$target->name}."
            );
        }

        if ($response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0') {
            $retryAfter = $response->header('Retry-After');
            $retryAfter = $retryAfter !== '' ? $retryAfter : $response->header('X-RateLimit-Reset');

            throw new GitHubRateLimitException(
                'A GitHub API rate limit elérve, később próbáld újra.',
                $retryAfter !== '' ? (int) $retryAfter : null,
            );
        }

        throw new GitHubApiException(
            "A GitHub API hívás sikertelen volt (HTTP {$response->status()})."
        );
    }

    private function nextPageUrl(Response $response): ?string
    {
        $link = $response->header('Link');

        if (! $link) {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (preg_match('/<(?<url>[^>]+)>;\s*rel="next"/', trim($part), $matches) === 1) {
                return $matches['url'];
            }
        }

        return null;
    }
}
