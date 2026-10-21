<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\GitHub\GitHubCommitReader;
use App\Models\GitHubAccount;
use App\Models\GitHubCommit;
use App\Models\GitHubInstallation;
use App\Models\GitHubRepository;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GitHubCommitAttributionTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_matches_only_stable_github_id_not_login_even_if_login_changes(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $developer = User::factory()->create(['name' => 'Developer in TaskFlow']);
        $this->addProjectMember($project, $developer);
        GitHubAccount::query()->create([
            'user_id' => $developer->id,
            'github_user_id' => 54321,
            'github_login' => 'new-github-login',
            'connected_at' => now(),
        ]);
        $this->commit($repository, 54321, 'old-github-login');

        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('projects/integrations/GitHub')
                ->where('commits.0.author', 'Original developer')
                ->where('commits.0.taskflow_author.id', $developer->id)
                ->where('commits.0.taskflow_author.name', 'Developer in TaskFlow')
                ->etc());
    }

    public function test_existing_commits_are_linked_after_oauth_connection_and_unlinked_after_disconnect(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $developer = User::factory()->create(['name' => 'Later member']);
        $this->addProjectMember($project, $developer);
        $this->commit($repository, 54321, 'same-handle');
        $url = route('projects.github.index', $project);
        $assertName = fn (?string $name) => $this->actingAs($owner)->get($url)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('commits.0.taskflow_author.name', $name)->etc());

        $this->actingAs($owner)->get($url)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('commits.0.taskflow_author', null)->etc());

        $account = GitHubAccount::query()->create([
            'user_id' => $developer->id, 'github_user_id' => 54321,
            'github_login' => 'same-handle', 'connected_at' => now(),
        ]);
        $assertName('Later member');

        $account->delete();
        $this->actingAs($owner)->get($url)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('commits.0.taskflow_author', null)->etc());
        $this->assertDatabaseCount('github_commits', 1);
    }

    public function test_name_or_login_similarity_does_not_claim_unidentified_commits(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $developer = User::factory()->create(['name' => 'Original developer']);
        $this->addProjectMember($project, $developer);
        GitHubAccount::query()->create([
            'user_id' => $developer->id, 'github_user_id' => 54321,
            'github_login' => 'same-handle', 'connected_at' => now(),
        ]);
        $this->commit($repository, null, 'same-handle');

        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('commits.0.taskflow_author', null)->etc());
    }

    public function test_unrelated_taskflow_user_is_not_exposed_in_another_project(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $stranger = User::factory()->create(['name' => 'Private account owner']);
        GitHubAccount::query()->create([
            'user_id' => $stranger->id, 'github_user_id' => 54321,
            'github_login' => 'stranger', 'connected_at' => now(),
        ]);
        $this->commit($repository, 54321, 'stranger');

        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('commits.0.taskflow_author', null)->etc());
    }

    public function test_disabled_taskflow_member_is_not_exposed_as_commit_author(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $developer = User::factory()->create(['name' => 'Disabled developer']);
        $this->addProjectMember($project, $developer);
        $developer->forceFill(['disabled_at' => now()])->save();
        GitHubAccount::query()->create([
            'user_id' => $developer->id, 'github_user_id' => 54321,
            'github_login' => 'developer', 'connected_at' => now(),
        ]);
        $this->commit($repository, 54321, 'developer');

        $this->actingAs($owner)->get(route('projects.github.index', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('commits.0.taskflow_author', null)->etc());
    }

    public function test_github_unknown_author_is_marked_checked_and_not_repeated(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $this->commit($repository, null, 'unresolved', false);
        $reader = Mockery::mock(GitHubCommitReader::class);
        $reader->shouldReceive('authorIdentitiesForStoredCommits')->once()
            ->andReturn([self::SHA => null]);
        $this->app->instance(GitHubCommitReader::class, $reader);

        $this->actingAs($owner)->post(route('projects.github.authors', $project))->assertRedirect();
        $this->assertDatabaseHas('github_commits', ['sha' => self::SHA, 'author_github_user_id' => null]);
        $this->assertNotNull(GitHubCommit::query()->where('sha', self::SHA)->firstOrFail()->author_identity_checked_at);
        $this->actingAs($owner)->post(route('projects.github.authors', $project))->assertRedirect();
    }

    public function test_legacy_author_backfill_is_limited_to_stored_commits_and_manager_permission(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $member = User::factory()->create();
        $this->addProjectMember($project, $member);
        for ($i = 1; $i <= 11; $i++) {
            GitHubCommit::query()->create([
                'github_repository_id' => $repository->id,
                'sha' => str_pad(dechex($i), 40, '0', STR_PAD_LEFT),
                'message' => 'Old commit '.$i,
            ]);
        }

        $reader = Mockery::mock(GitHubCommitReader::class);
        $reader->shouldReceive('authorIdentitiesForStoredCommits')
            ->once()->with(456, 'example/api', 123, Mockery::on(fn ($shas): bool => count($shas) === 10))
            ->andReturn(array_fill_keys(array_map(fn ($i) => str_pad(dechex($i), 40, '0', STR_PAD_LEFT), range(1, 10)), 54321));
        $this->app->instance(GitHubCommitReader::class, $reader);

        $url = route('projects.github.authors', $project);
        $this->actingAs($member)->post($url)->assertForbidden();
        $this->actingAs($owner)->post($url)->assertRedirect(route('projects.github.index', $project));

        $this->assertSame(1, GitHubCommit::query()->where('github_repository_id', $repository->id)->whereNull('author_identity_checked_at')->count());
        $this->assertSame(10, GitHubCommit::query()->where('author_github_user_id', 54321)->count());
        $this->assertDatabaseCount('github_commits', 11);
        $this->assertDatabaseCount('github_sync_runs', 0);
        $this->assertSame(self::SHA, $repository->fresh()->last_synced_sha);
    }

    public function test_failed_legacy_batch_does_not_mark_any_commit_checked(): void
    {
        [$project, $owner, $repository] = $this->linkedProject();
        $this->commit($repository, null, 'legacy', false);
        $reader = Mockery::mock(GitHubCommitReader::class);
        $reader->shouldReceive('authorIdentitiesForStoredCommits')->once()
            ->andThrow(new RuntimeException('Bad GitHub response'));
        $this->app->instance(GitHubCommitReader::class, $reader);

        $this->actingAs($owner)->post(route('projects.github.authors', $project))
            ->assertSessionHasErrors('authors');
        $this->assertDatabaseHas('github_commits', [
            'sha' => self::SHA, 'author_github_user_id' => null,
            'author_identity_checked_at' => null,
        ]);
    }

    /** @return array{Project, User, GitHubRepository} */
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
        $installation = GitHubInstallation::query()->create([
            'workspace_id' => $project->workspace_id,
            'installation_id' => 456,
            'github_account_id' => 78,
            'account_login' => 'example',
            'account_type' => 'Organization',
            'connected_at' => now(),
        ]);
        $repository = GitHubRepository::query()->create([
            'project_id' => $project->id,
            'github_installation_id' => $installation->id,
            'github_repository_id' => 123,
            'full_name' => 'example/api',
            'default_branch' => 'main',
            'start_sha' => self::SHA,
            'last_synced_sha' => self::SHA,
            'connected_at' => now(),
        ]);
        return [$project, $owner, $repository];
    }

    private function addProjectMember(Project $project, User $user): void
    {
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MEMBER,
        ]);
    }

    private function commit(GitHubRepository $repository, ?int $githubUserId, string $login, bool $checked = true): void
    {
        GitHubCommit::query()->create([
            'github_repository_id' => $repository->id,
            'sha' => self::SHA,
            'message' => 'Change',
            'author_name' => 'Original developer',
            'author_login' => $login,
            'author_github_user_id' => $githubUserId,
            'author_identity_checked_at' => $checked ? now() : null,
            'committed_at' => now(),
        ]);
    }
}
