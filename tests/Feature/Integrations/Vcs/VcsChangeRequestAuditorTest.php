<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\VcsChangeRequestAuditor;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VcsChangeRequestAuditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_repository_and_pending_reviews_pass_without_writes(): void
    {
        [$project, $repo] = $this->repository();
        $pr = $this->pull($repo);
        $pr->reviews()->create(['external_review_id' => '1', 'state' => 'approved', 'submitted_at' => now()]);
        $pr->reviews()->create(['external_review_id' => '2', 'state' => 'pending']);
        $before = DB::table('repository_change_requests')->where('id', $pr->id)->first();

        $result = app(VcsChangeRequestAuditor::class)->inspect($project->id);
        $this->assertSame([], $result['issues']);
        $this->assertSame(['pull_requests' => 1, 'reviews' => 2], $result['counts']);
        $this->artisan('vcs:audit-prs', ['--project' => $project->id])
            ->expectsOutput('PR/MR audit passed. No records were changed.')
            ->assertExitCode(0);
        $this->assertEquals($before, DB::table('repository_change_requests')->where('id', $pr->id)->first());
    }

    public function test_audit_flags_invalid_statuses_merge_dates_and_review_dates(): void
    {
        [, $repo] = $this->repository();
        $pr = $this->pull($repo);
        $pr->forceFill(['state' => 'merged', 'merged_at' => null])->save();
        $pr->reviews()->create(['external_review_id' => 'invalid-state', 'state' => 'unknown']);
        $pr->reviews()->create(['external_review_id' => 'no-date', 'state' => 'approved']);

        $result = app(VcsChangeRequestAuditor::class)->inspect();
        $this->assertCount(3, $result['issues']);
        $this->assertTrue(collect($result['issues'])->contains(fn ($message) => str_contains($message, 'merge dates')));
        $this->artisan('vcs:audit-prs')->assertExitCode(1);
        $this->assertDatabaseHas('repository_change_request_reviews', ['external_review_id' => 'no-date']);
    }

    public function test_scope_is_restricted_to_requested_project_and_rejects_unknown_project(): void
    {
        [$firstProject, $firstRepo] = $this->repository();
        [, $secondRepo] = $this->repository();
        $this->pull($firstRepo);
        $other = $this->pull($secondRepo);
        $other->forceFill(['state' => 'invalid'])->save();

        $scoped = app(VcsChangeRequestAuditor::class)->inspect($firstProject->id);
        $this->assertSame(['pull_requests' => 1, 'reviews' => 0], $scoped['counts']);
        $this->assertSame([], $scoped['issues']);
        $this->artisan('vcs:audit-prs', ['--project' => 999999])->assertExitCode(1);
        $this->artisan('vcs:audit-prs', ['--project' => $firstProject->id])->assertExitCode(0);
        $this->artisan('vcs:audit-prs')->assertExitCode(1);
    }

    /** @return array{Project, RepositoryConnection} */
    private function repository(): array
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => (string) (1000 + $project->id),
            'account_login' => 'example',
            'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => (string) (2000 + $project->id),
            'full_name' => 'example/repo',
            'default_branch' => 'main',
            'start_sha' => str_repeat('a', 40),
            'last_synced_sha' => str_repeat('a', 40),
            'connected_at' => now(),
        ]);
        return [$project, $repository];
    }

    private function pull(RepositoryConnection $repository): RepositoryChangeRequest
    {
        return $repository->changeRequests()->create([
            'external_change_request_id' => '55',
            'number' => 1,
            'title' => 'Example',
            'web_url' => 'https://github.com/example/repo/pull/1',
            'state' => 'open',
            'remote_updated_at' => now(),
        ]);
    }
}
