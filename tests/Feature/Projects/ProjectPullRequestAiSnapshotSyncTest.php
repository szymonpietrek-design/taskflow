<?php

namespace Tests\Feature\Projects;

use App\Enums\WorkspaceRole;
use App\Integrations\GitHub\GitHubPullRequestFileReader;
use App\Integrations\GitHub\GitHubPullRequestReader;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositorySyncLock;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/** End-to-end HTTP contract for the one-click PR + file snapshot refresh. */
class ProjectPullRequestAiSnapshotSyncTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HEAD_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unchanged_old_pr_is_refreshed_even_when_github_updated_at_did_not_change(): void
    {
        [$project, $repository, $owner, $pull] = $this->fixture();
        $originalRemoteUpdate = $pull->remote_updated_at?->toDateTimeString();
        $this->githubPull()->shouldReceive('pullRequest')->once()->with(10, 'example/repo', 20, 10)
            ->andReturn($this->snapshot());
        $this->githubFiles()->shouldReceive('filesForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, [['number' => 10, 'head_sha' => self::HEAD_A]])
            ->andReturn([10 => [$this->file('README.md')]]);

        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page->where('ai.ready', false)->etc());
        $this->actingAs($owner)->post(route('projects.github.pull.sync', [$project, 10]))
            ->assertRedirect(route('projects.github.pull.show', [$project, 10]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($pull->fresh()->files_synced_at);
        $this->assertTrue($pull->fresh()->synced_at->gte(now()->subMinutes(1)));
        $this->assertSame($originalRemoteUpdate, $pull->fresh()->remote_updated_at?->toDateTimeString());
        $this->assertSame(self::HEAD_A, $repository->fresh()->last_synced_sha); // commit cursor untouched
        $this->assertSame(['README.md'], $pull->files()->pluck('path')->all());
        $this->assertDatabaseCount('pull_request_ai_analyses', 0); // no AI request or consent implied

        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ai.ready', true)
                ->where('files_current', true)
                ->where('files_count', 1)
                ->where('files.0.path', 'README.md')
                ->etc());
    }

    public function test_new_head_is_saved_first_and_files_are_bound_to_new_revision(): void
    {
        [$project, $repository, $owner, $pull] = $this->fixture();
        $pull->forceFill(['files_head_sha' => self::HEAD_A, 'files_synced_at' => now()->subHours(2)])->save();
        $pull->files()->create($this->file('outdated.php'));
        $this->githubPull()->shouldReceive('pullRequest')->once()->andReturn($this->snapshot(self::HEAD_B));
        $this->githubFiles()->shouldReceive('filesForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, [['number' => 10, 'head_sha' => self::HEAD_B]])
            ->andReturn([10 => [$this->file('current.php')]]);

        $this->actingAs($owner)->post(route('projects.github.pull.sync', [$project, 10]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(self::HEAD_B, $pull->fresh()->head_sha);
        $this->assertSame(self::HEAD_B, $pull->fresh()->files_head_sha);
        $this->assertSame(['current.php'], $pull->files()->pluck('path')->all());
        $this->assertSame(self::HEAD_A, $repository->fresh()->last_synced_sha);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ai.ready', true)
                ->where('pull_request.head_sha', self::HEAD_B)
                ->etc());
    }

    public function test_file_fetch_failure_after_head_change_keeps_ai_disabled(): void
    {
        [$project, , $owner, $pull] = $this->fixture();
        $pull->forceFill(['files_head_sha' => self::HEAD_A, 'files_synced_at' => now()->subHours(2)])->save();
        $this->githubPull()->shouldReceive('pullRequest')->once()->andReturn($this->snapshot(self::HEAD_B));
        $this->githubFiles()->shouldReceive('filesForStoredPullRequests')->once()
            ->andThrow(new RuntimeException('SECRET_FROM_GITHUB'));

        $response = $this->actingAs($owner)->post(route('projects.github.pull.sync', [$project, 10]));
        $response->assertSessionHasErrors('sync');
        $this->assertStringNotContainsString('SECRET_FROM_GITHUB', json_encode(session('errors')?->getBag('default')->all()));
        $this->assertSame(self::HEAD_B, $pull->fresh()->head_sha);
        $this->assertSame(self::HEAD_A, $pull->fresh()->files_head_sha);
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 10]))
            ->assertInertia(fn (Assert $page) => $page->where('ai.ready', false)->etc());
    }

    public function test_wrong_pr_identity_is_not_saved_and_files_are_not_called(): void
    {
        [$project, , $owner, $pull] = $this->fixture();
        $this->githubPull()->shouldReceive('pullRequest')->once()
            ->andReturn($this->snapshot(self::HEAD_B, ['id' => 987654]));
        $this->githubFiles()->shouldNotReceive('filesForStoredPullRequests');

        $this->actingAs($owner)->post(route('projects.github.pull.sync', [$project, 10]))
            ->assertSessionHasErrors('sync');
        $this->assertSame(self::HEAD_A, $pull->fresh()->head_sha);
        $this->assertNull($pull->fresh()->synced_at);
    }

    public function test_permissions_and_project_scoping_are_enforced_before_network_access(): void
    {
        [$project, , $owner] = $this->fixture();
        $outsider = User::factory()->create();
        $this->githubPull()->shouldNotReceive('pullRequest');
        $this->githubFiles()->shouldNotReceive('filesForStoredPullRequests');

        $url = route('projects.github.pull.sync', [$project, 10]);
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs($outsider)->post($url)->assertForbidden();
        $this->actingAs($owner)->post(route('projects.github.pull.sync', [$project, 11]))->assertNotFound();
        $project->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner)->post($url)->assertForbidden();
    }

    public function test_repository_sync_lock_blocks_competing_refresh(): void
    {
        [$project, $repository, $owner, $pull] = $this->fixture();
        $this->githubPull()->shouldNotReceive('pullRequest');
        $this->githubFiles()->shouldNotReceive('filesForStoredPullRequests');
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        $this->assertTrue($lock->get());
        try {
            $this->actingAs($owner)->post(route('projects.github.pull.sync', [$project, 10]))
                ->assertSessionHasErrors('sync');
        } finally {
            $lock->release();
        }
        $this->assertNull($pull->fresh()->synced_at);
    }

    private function githubPull(): Mockery\MockInterface
    {
        $reader = Mockery::mock(GitHubPullRequestReader::class);
        $this->app->instance(GitHubPullRequestReader::class, $reader);
        return $reader;
    }

    private function githubFiles(): Mockery\MockInterface
    {
        $reader = Mockery::mock(GitHubPullRequestFileReader::class);
        $this->app->instance(GitHubPullRequestFileReader::class, $reader);
        return $reader;
    }

    /** @return array{Project, RepositoryConnection, User, RepositoryChangeRequest} */
    private function fixture(): array
    {
        $project = Project::factory()->create();
        $owner = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER, 'suspended_at' => null,
        ]);
        $vcs = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '10', 'account_login' => 'example', 'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $vcs->id,
            'external_repository_id' => '20', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::HEAD_A,
            'last_synced_sha' => self::HEAD_A, 'connected_at' => now(),
        ]);
        $pull = $repository->changeRequests()->create([
            'external_change_request_id' => '501', 'number' => 10, 'title' => 'PR #10',
            'state' => 'open', 'web_url' => 'https://github.com/example/repo/pull/10',
            'head_sha' => self::HEAD_A, 'remote_updated_at' => '2026-10-21 09:00:00',
            'synced_at' => null,
        ]);
        return [$project, $repository, $owner, $pull];
    }

    private function snapshot(string $head = self::HEAD_A, array $overrides = []): array
    {
        return array_replace([
            'id' => 501, 'number' => 10, 'title' => 'PR #10',
            'body' => 'Description', 'state' => 'open', 'draft' => false, 'locked' => false,
            'created_at' => '2026-10-20T08:00:00Z', 'updated_at' => '2026-10-21T09:00:00Z',
            'closed_at' => null, 'merged_at' => null, 'merge_commit_sha' => null,
            'head' => ['sha' => $head, 'ref' => 'branch', 'repo' => ['full_name' => 'example/repo']],
            'base' => ['sha' => self::HEAD_A, 'ref' => 'main'],
            'user' => ['id' => 123, 'login' => 'developer'],
        ], $overrides);
    }

    private function file(string $path): array
    {
        return [
            'path' => $path, 'path_hash' => hash('sha256', $path), 'previous_path' => null,
            'status' => 'modified', 'additions' => 1, 'deletions' => 2, 'changes' => 3,
            'blob_sha' => null, 'patch' => null, 'patch_truncated' => false,
        ];
    }
}
