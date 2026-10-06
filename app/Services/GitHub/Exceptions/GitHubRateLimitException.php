<?php

namespace App\Services\GitHub\Exceptions;

class GitHubRateLimitException extends GitHubApiException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
