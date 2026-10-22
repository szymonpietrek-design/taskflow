<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryChangeRequestParticipantResolver;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\UserVcsAccount;
use App\Models\VcsConnection;
use App\Models\VcsInstance;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RepositoryChangeRequestParticipantResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_author_and_merger_by_verified_id_not_login_and_unlinks_dynamically(): void
    {
        [$project, $repository] = $this->repository();
        $author = $this->member($project, 'Author in TaskFlow');
        $merger = $this->member($project, 'Merger in TaskFlow');
        $this->account($author, '45', 'renamed-author');
        $this->account($merger, '55', 'renamed-merger');
        $pull = $this->pull($repository, ['author_external_user_id' => '45', 'author_login' => 'old-author',
            'merged_by_external_user_id' => '55', 'merged_by_login' => 'old-merger']);
        $resolver = app(RepositoryChangeRequestParticipantResolver::class);

        $match = $resolver->resolve($repository, collect([$pull]))[$pull->id];
        $this->assertSame(['id' => $author->id, 'name' => $author->name], $match['author']);
        $this->assertSame(['id' => $merger->id, 'name' => $merger->name], $match['merged_by']);

        UserVcsAccount::query()->where('user_id', $merger->id)->delete();
        $this->assertNull($resolver->resolve($repository, collect([$pull]))[$pull->id]['merged_by']);
    }

    public function test_filters_disabled_nonmembers_and_unverified_other_instances(): void
    {
        [$project, $repository] = $this->repository();
        $disabled = $this->member($project, 'Disabled');
        $disabled->forceFill(['disabled_at' => now()])->save();
        $this->account($disabled, '45', 'disabled');
        $outside = User::factory()->create(['name' => 'Outside']);
        $this->account($outside, '55', 'outside');
        $wrongInstance = $this->member($project, 'Wrong instance');
        $gitlab = VcsInstance::query()->create([
            'provider' => 'gitlab', 'instance_key' => hash('sha256', 'https://gitlab.com'), 'base_url' => 'https://gitlab.com',
        ]);
        UserVcsAccount::query()->create([
            'user_id' => $wrongInstance->id, 'vcs_instance_id' => $gitlab->id,
            'external_user_id' => '65', 'login' => 'someone', 'connected_at' => now(),
        ]);
        $pull = $this->pull($repository, [
            'author_external_user_id' => '45', 'merged_by_external_user_id' => '55',
        ]);
        $resolver = app(RepositoryChangeRequestParticipantResolver::class);
        $this->assertSame(['author' => null, 'merged_by' => null],
            $resolver->resolve($repository, collect([$pull]))[$pull->id]);

        $pull->forceFill(['author_external_user_id' => '65'])->save();
        $this->assertNull($resolver->resolve($repository, collect([$pull->fresh()]))[$pull->id]['author']);
    }

    public function test_workspace_admin_can_be_attributed_but_suspended_admin_cannot(): void
    {
        [$project, $repository] = $this->repository();
        $admin = User::factory()->create(['name' => 'Workspace administrator']);
        $membership = WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
            'suspended_at' => null,
        ]);
        $this->account($admin, '45', 'admin');
        $pull = $this->pull($repository, ['author_external_user_id' => '45']);
        $resolver = app(RepositoryChangeRequestParticipantResolver::class);
        $this->assertSame($admin->id, $resolver->resolve($repository, collect([$pull]))[$pull->id]['author']['id']);

        $membership->forceFill(['suspended_at' => now()])->save();
        $this->assertNull($resolver->resolve($repository, collect([$pull]))[$pull->id]['author']);
    }

    public function test_rejects_requests_from_other_repositories(): void
    {
        [, $repository] = $this->repository();
        [, $other] = $this->repository();
        $pull = $this->pull($other, []);
        $this->expectException(RuntimeException::class);
        app(RepositoryChangeRequestParticipantResolver::class)->resolve($repository, collect([$pull]));
    }

    public function test_does_not_attribute_merger_to_unmerged_pull(): void
    {
        [$project, $repository] = $this->repository();
        $merger = $this->member($project, 'Merger');
        $this->account($merger, '55', 'merged');
        $pull = $this->pull($repository, [
            'state' => RepositoryChangeRequest::STATE_OPEN,
            'merged_at' => null,
            'merged_by_external_user_id' => '55',
        ]);
        $this->assertNull(app(RepositoryChangeRequestParticipantResolver::class)
            ->resolve($repository, collect([$pull]))[$pull->id]['merged_by']);
    }

    private function repository(): array
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => (string) (1000 + $project->id),
            'account_login' => 'example', 'connected_at' => now(),
        ]);
        return [$project, RepositoryConnection::query()->create([
            'project_id' => $project->id,
            'vcs_connection_id' => $connection->id,
            'external_repository_id' => (string) (2000 + $project->id),
            'full_name' => 'example/repo', 'default_branch' => 'main',
            'start_sha' => str_repeat('a', 40),
            'last_synced_sha' => str_repeat('a', 40),
            'connected_at' => now(),
        ])];
    }

    private function member(Project $project, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => ProjectRole::MEMBER]);
        return $user;
    }

    private function account(User $user, string $id, string $login): void
    {
        UserVcsAccount::query()->create([
            'user_id' => $user->id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_user_id' => $id, 'login' => $login, 'connected_at' => now(),
        ]);
    }

    private function pull(RepositoryConnection $repository, array $overrides): RepositoryChangeRequest
    {
        return $repository->changeRequests()->create(array_replace([
            'external_change_request_id' => (string) (3000 + $repository->id),
            'number' => 2,
            'title' => 'PR in project',
            'web_url' => 'https://github.com/example/repo/pull/2',
            'state' => RepositoryChangeRequest::STATE_MERGED,
            'author_external_user_id' => '45',
            'merged_by_external_user_id' => '55',
            'merged_at' => now(),
        ], $overrides));
    }
}
