<?php

namespace App\Contracts\Ai;

use App\Services\Ai\GeneratedPullRequestAnalysis;

/** External AI boundary; always mock this interface in application tests. */
interface PullRequestAnalysisGenerator
{
    /** @param array<string, mixed> $evidence */
    public function generate(array $evidence, string $language): GeneratedPullRequestAnalysis;
}
