<?php

namespace Tests\Unit\Services\Ai;

use App\Support\Reports\ReportVcsCountGuard;
use InvalidArgumentException;
use Tests\TestCase;

final class ReportVcsCountGuardTest extends TestCase
{
    public function test_it_allows_source_counts_and_subsets_but_not_fabricated_extra_files_as_commits(): void
    {
        $guard = new ReportVcsCountGuard;
        $context = '{"vcs":{"commit_count":18,"pull_request_count":1}}';
        $guard->assertPlausible('Zarejestrowano 18 commitów i 1 PR.', $context, 'pl');
        $guard->assertPlausible('Uwzględniono 3 commity spośród zmian.', $context, 'pl');
        $guard->assertPlausible('Zmieniono 20 plików.', $context, 'pl');
        $guard->assertPlausible('There were 18 commits and 1 PR.', $context, 'en');
        $guard->assertPlausible('There were 20 commits.', '{"tasks":[]}', 'en');
        $this->addToAssertionCount(5);
    }

    public function test_it_rejects_explicit_commit_overcounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReportVcsCountGuard)->assertPlausible('Wykonano 20 commitów.', '{"vcs":{"commit_count":18,"pull_request_count":1}}', 'pl');
    }

    public function test_it_rejects_explicit_pull_request_overcounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReportVcsCountGuard)->assertPlausible('Merged 2 PRs.', '{"vcs":{"commit_count":18,"pull_request_count":1}}', 'en');
    }
}
