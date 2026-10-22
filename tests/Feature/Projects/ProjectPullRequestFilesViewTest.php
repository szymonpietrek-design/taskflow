<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\GitHub\GitHubPullRequestFileReader;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\VcsConnection;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProjectPullRequestFilesViewTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_current_file_snapshot_is_visible_without_raw_provider_html(): void
    {
        [$project, $repo, $owner] = $this->repository();
        $pull = $this->pull($repo);
        $pull->files()->create([
            'path' => 'src/Example.php', 'path_hash' => hash('sha256', 'src/Example.php'),
            'status' => 'modified', 'additions' => 2, 'deletions' => 1,
            'changes' => 3, 'patch' => "@@ -1 +1 @@\n-foo\n+bar", 'patch_truncated' => false,
        ]);
        $pull->forceFill(['files_head_sha' => self::HEAD, 'files_synced_at' => now()])->save();
        $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 3]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('files_current', true)
                ->where('files_count', 1)
                ->where('files.0.path', 'src/Example.php')
                ->where('files.0.patch', "@@ -1 +1 @@\n-foo\n+bar")
                ->missing('files.0.raw_url')
                ->etc());
    }

    public function test_stale_file_snapshot_is_not_shown_under_new_head(): void
    {
        [$project, $repo, $owner] = $this->repository();
        $pull = $this->pull($repo);
        $pull->files()->create([
            'path' => 'private.php', 'path_hash' => hash('sha256', 'private.php'),
            'status' => 'modified', 'additions' => 1, 'deletions' => 0, 'changes' => 1,
            'patch' => 'STALE_SECRET', 'patch_truncated' => false,
        ]);
        $pull->forceFill(['files_head_sha' => str_repeat('b', 40), 'files_synced_at' => now()])->save();
        $response = $this->actingAs($owner)->get(route('projects.github.pull.show', [$project, 3]));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('files_current', false)->where('files_count', 0)->has('files', 0)->etc());
        $this->assertStringNotContainsString('STALE_SECRET', $response->getContent());
    }

    public function test_regular_member_cannot_sync_files_but_can_view_details(): void
    {
        [$project, $repo] = $this->repository();
        $pull = $this->pull($repo);
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::MEMBER,
        ]);
        $this->actingAs($member)->get(route('projects.github.pull.show', [$project, 3]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_sync_files', false)->etc());
        $this->actingAs($member)->post(route('projects.github.pull.files.sync', [$project, 3]))->assertForbidden();
    }

    public function test_admin_can_sync_only_selected_pull_and_errors_do_not_leak(): void
    {
        [$project, $repo, $owner] = $this->repository();
        $this->pull($repo);
        $mock = Mockery::mock(GitHubPullRequestFileReader::class);
        $mock->shouldReceive('filesForStoredPullRequests')->once()
            ->with(10, 'example/repo', 20, [['number' => 3, 'head_sha' => self::HEAD]])
            ->andReturn([3 => []]);
        $this->app->instance(GitHubPullRequestFileReader::class, $mock);
        $this->actingAs($owner)->post(route('projects.github.pull.files.sync', [$project, 3]))
            ->assertRedirect(route('projects.github.pull.show', [$project, 3]));
        $this->assertSame(self::HEAD, $repo->changeRequests()->first()->files_head_sha);

        $mock = Mockery::mock(GitHubPullRequestFileReader::class);
        $mock->shouldReceive('filesForStoredPullRequests')->once()
            ->andThrow(new RuntimeException('SECRET_TOKEN_IN_ERROR'));
        $this->app->instance(GitHubPullRequestFileReader::class, $mock);
        $this->actingAs($owner)->post(route('projects.github.pull.files.sync', [$project, 3]))
            ->assertSessionHasErrors(['files']);
        $this->assertStringNotContainsString('SECRET_TOKEN_IN_ERROR', implode(' ', session('errors')->get('files')));
    }

    public function test_other_project_pr_cannot_be_synchronized(): void
    {
        [$project, $repo, $owner] = $this->repository();
        $this->pull($repo);
        $this->actingAs($owner)->post(route('projects.github.pull.files.sync', [$project, 99]))->assertNotFound();
    }

    private function repository(): array
    {
        $project = Project::factory()->create();
        $owner = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id, 'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER, 'suspended_at' => null,
        ]);
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '10', 'account_login' => 'example', 'connected_at' => now(),
        ]);
        $repo = RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $connection->id,
            'external_repository_id' => '20', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::HEAD,
            'last_synced_sha' => self::HEAD, 'connected_at' => now(),
        ]);
        return [$project, $repo, $owner];
    }

    private function pull(RepositoryConnection $repo): RepositoryChangeRequest
    {
        return $repo->changeRequests()->create([
            'external_change_request_id' => '1003', 'number' => 3,
            'title' => 'Example PR', 'web_url' => 'https://github.com/example/repo/pull/3',
            'state' => 'open', 'head_sha' => self::HEAD, 'remote_updated_at' => now(),
        ]);
    }
}
