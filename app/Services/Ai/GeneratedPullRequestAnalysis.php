<?php

namespace App\Services\Ai;

/** The normalized provider output. Never contains a raw provider response or prompt. */
final readonly class GeneratedPullRequestAnalysis
{
    public function __construct(
        public string $markdown,
        public string $provider,
        public string $model,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}
}
