<?php

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final readonly class GitHubRepositoryData
{
    public function __construct(
        public int $githubId,
        public string $name,
        public string $fullName,
        public ?string $description,
        public string $url,
        public ?string $language,
        public int $stargazersCount,
        public int $openIssuesCount,
        public bool $isArchived,
        public ?CarbonInterface $githubUpdatedAt,
    ) {}

    /**
     * Map a single repository entry from the GitHub API JSON response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromGitHubResponse(array $data): self
    {
        return new self(
            githubId: (int) $data['id'],
            name: (string) $data['name'],
            fullName: (string) $data['full_name'],
            description: $data['description'] ?? null,
            url: (string) $data['html_url'],
            language: $data['language'] ?? null,
            stargazersCount: (int) ($data['stargazers_count'] ?? 0),
            openIssuesCount: (int) ($data['open_issues_count'] ?? 0),
            isArchived: (bool) ($data['archived'] ?? false),
            githubUpdatedAt: isset($data['pushed_at']) ? Carbon::parse($data['pushed_at']) : null,
        );
    }

    /**
     * Convert to the attribute array used when persisting the repository.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'github_id' => $this->githubId,
            'name' => $this->name,
            'full_name' => $this->fullName,
            'description' => $this->description,
            'url' => $this->url,
            'language' => $this->language,
            'stargazers_count' => $this->stargazersCount,
            'open_issues_count' => $this->openIssuesCount,
            'is_archived' => $this->isArchived,
            'github_updated_at' => $this->githubUpdatedAt,
        ];
    }
}
