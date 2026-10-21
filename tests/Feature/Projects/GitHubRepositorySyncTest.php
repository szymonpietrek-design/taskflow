<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\GitHub\GitHubCommitReader;
use App\Models\RepositoryCommit;
use App\Models\VcsConnection;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\RepositoryConnection;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GitHubRepositorySyncTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HEAD = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_first_sync_imports_only_new_commits_and_advances_cursor(): void
    {
        [$project, $owner, $repo] = $this->linkedProject();
        $this->reader([self::HEAD, [
            $this->commit(str_repeat('c', 40), 'Add search'),
            $this->commit(self::HEAD, 'Fix filters'),
        ]]);

        $this->actingAs($owner)->post(route('projects.github.sync', $project))
            ->assertRedirect(route('projects.github.index', $project));

        $this->assertDatabaseCount('repository_commits', 2);
        $this->assertDatabaseHas('repository_commits', ['repository_connection_id' => $repo->id, 'sha' => self::HEAD, 'author_external_user_id' => '54321']);
        $this->assertDatabaseHas('repository_connections', ['id' => $repo->id, 'start_sha' => self::BASE, 'last_synced_sha' => self::HEAD]);
        $this->assertDatabaseHas('repository_sync_runs', ['repository_connection_id' => $repo->id, 'status' => 'completed', 'imported_count' => 2]);
    }

    public function test_no_changes_does_not_duplicate_commits_and_still_records_sync(): void
    {
        [$project, $owner, $repo] = $this->linkedProject();
        $this->reader([self::HEAD, [$this->commit(self::HEAD, 'Fix filters')]], [self::HEAD, []]);
        $url = route('projects.github.sync', $project);
        $this->actingAs($owner)->post($url)->assertRedirect();
        $this->actingAs($owner)->post($url)->assertRedirect();

        $this->assertDatabaseCount('repository_commits', 1);
        $this->assertDatabaseCount('repository_sync_runs', 2);
        $this->assertDatabaseHas('repository_sync_runs', ['status' => 'completed', 'imported_count' => 0, 'fetched_count' => 0]);
        $this->assertSame(self::HEAD, $repo->fresh()->last_synced_sha);
    }

    public function test_comparison_failure_preserves_cursor_and_records_retryable_error(): void
    {
        [$project, $owner, $repo] = $this->linkedProject();
        $reader = Mockery::mock(GitHubCommitReader::class);
        $reader->shouldReceive('changesSince')->once()->andThrow(new RuntimeException('GitHub branch history was rewritten or diverged.'));
        $this->app->instance(GitHubCommitReader::class, $reader);

        $this->actingAs($owner)->from(route('projects.github.index', $project))
            ->post(route('projects.github.sync', $project))
            ->assertRedirect(route('projects.github.index', $project))->assertSessionHasErrors('sync');

        $this->assertDatabaseCount('repository_commits', 0);
        $this->assertDatabaseHas('repository_connections', ['id' => $repo->id, 'last_synced_sha' => self::BASE, 'last_synced_at' => null]);
        $this->assertDatabaseHas('repository_sync_runs', ['repository_connection_id' => $repo->id, 'status' => 'failed']);
    }

    public function test_only_workspace_owner_or_admin_can_trigger_sync(): void
    {
        [$project, $owner] = $this->linkedProject();
        $member = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::MEMBER]);
        $reader = Mockery::mock(GitHubCommitReader::class);
        $reader->shouldNotReceive('changesSince');
        $this->app->instance(GitHubCommitReader::class, $reader);

        $this->actingAs($member)->post(route('projects.github.sync', $project))->assertForbidden();
        $this->assertDatabaseCount('repository_sync_runs', 0);

        // An archived project is read-only, including synchronization.
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->post(route('projects.github.sync', $project))->assertForbidden();
    }

    public function test_project_member_can_view_imported_commits_without_sync_button_permission(): void
    {
        [$project, , $repo] = $this->linkedProject();
        RepositoryCommit::query()->create([
            'repository_connection_id' => $repo->id, 'sha' => self::HEAD,
            'message' => 'Fix tasks', 'author_name' => 'Dev', 'committed_at' => now(),
        ]);
        $member = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::MEMBER]);
        $this->actingAs($member)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('projects/integrations/GitHub')
                ->where('can_connect', false)
                ->where('commits_count', 1)
                ->has('commits', 1)->etc());
    }

    /** @return array{Project, User, RepositoryConnection} */
    private function linkedProject(): array
    {
        $project = Project::factory()->create();
        $owner = User::factory()->create(['is_system_admin' => true]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
            'suspended_at' => null,
        ]);
        $installation = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => '456',
            'external_account_id' => '78',
            'account_login' => 'example',
            'account_type' => 'Organization',
            'connected_at' => now(),
        ]);
        $repo = RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $installation->id,
            'external_repository_id' => '123',
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'start_sha' => self::BASE,
            'last_synced_sha' => self::BASE,
            'connected_at' => now(),
        ]);
        return [$project, $owner, $repo];
    }

    /** @return array{sha:string,message:string,author_name:?string,author_login:?string,committed_at:?string} */
    private function commit(string $sha, string $message): array
    {
        return ['sha' => $sha, 'message' => $message, 'author_name' => 'Dev', 'author_login' => 'dev', 'author_github_user_id' => 54321, 'committed_at' => '2026-10-21T09:00:00Z'];
    }

    /** @param array{string, array} ...$responses */
    private function reader(array ...$responses): void
    {
        $reader = Mockery::mock(GitHubCommitReader::class);
        $reader->shouldReceive('changesSince')->times(count($responses))
            ->andReturn(...array_map(fn (array $entry): array => ['head_sha' => $entry[0], 'commits' => $entry[1]], $responses));
        $this->app->instance(GitHubCommitReader::class, $reader);
    }
}
