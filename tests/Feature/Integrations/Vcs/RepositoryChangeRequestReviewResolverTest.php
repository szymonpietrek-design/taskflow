<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Integrations\Vcs\RepositoryChangeRequestReviewResolver;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RepositoryChangeRequestReview;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Models\UserVcsAccount;
use App\Models\VcsConnection;
use App\Models\VcsInstance;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RepositoryChangeRequestReviewResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_verified_reviewer_by_id_even_when_login_changed_and_unlinks_dynamically(): void
    {
        [$project, $repository] = $this->repository();
        $user = User::factory()->create(['name' => 'Real reviewer']);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => ProjectRole::MEMBER]);
        $this->account($user, '45', 'new-login');
        $review = $this->review($repository, '45', 'old-login');
        $resolver = app(RepositoryChangeRequestReviewResolver::class);

        $this->assertSame(['id' => $user->id, 'name' => 'Real reviewer'],
            $resolver->resolve($repository, collect([$review]))[$review->id]);
        UserVcsAccount::query()->where('user_id', $user->id)->delete();
        $this->assertNull($resolver->resolve($repository, collect([$review]))[$review->id]);
    }

    public function test_never_assigns_reviews_to_outside_disabled_or_other_instance_users(): void
    {
        [$project, $repository] = $this->repository();
        $outside = User::factory()->create();
        $this->account($outside, '45', 'reviewer');
        $outsideReview = $this->review($repository, '45', 'reviewer');
        $this->assertNull(app(RepositoryChangeRequestReviewResolver::class)
            ->resolve($repository, collect([$outsideReview]))[$outsideReview->id]);

        $projectMember = User::factory()->create(['disabled_at' => now()]);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $projectMember->id, 'role' => ProjectRole::MEMBER]);
        $this->account($projectMember, '46', 'reviewer');
        $disabledReview = $this->review($repository, '46', 'reviewer', 102);
        $this->assertNull(app(RepositoryChangeRequestReviewResolver::class)
            ->resolve($repository, collect([$disabledReview]))[$disabledReview->id]);

        $other = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $other->id, 'role' => ProjectRole::MEMBER]);
        $gitlab = VcsInstance::query()->create([
            'provider' => 'gitlab', 'instance_key' => hash('sha256', 'https://gitlab.example'),
            'base_url' => 'https://gitlab.example',
        ]);
        UserVcsAccount::query()->create([
            'user_id' => $other->id, 'vcs_instance_id' => $gitlab->id,
            'external_user_id' => '47', 'login' => 'other', 'connected_at' => now(),
        ]);
        $otherInstanceReview = $this->review($repository, '47', 'other', 103);
        $this->assertNull(app(RepositoryChangeRequestReviewResolver::class)
            ->resolve($repository, collect([$otherInstanceReview]))[$otherInstanceReview->id]);
    }

    public function test_workspace_admin_is_allowed_but_suspended_admin_is_not(): void
    {
        [$project, $repository] = $this->repository();
        $admin = User::factory()->create(['name' => 'Administrator']);
        $membership = WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::ADMIN,
            'suspended_at' => null,
        ]);
        $this->account($admin, '45', 'admin');
        $review = $this->review($repository, '45', 'admin');
        $resolver = app(RepositoryChangeRequestReviewResolver::class);
        $this->assertSame($admin->id, $resolver->resolve($repository, collect([$review]))[$review->id]['id']);
        $membership->forceFill(['suspended_at' => now()])->save();
        $this->assertNull($resolver->resolve($repository, collect([$review]))[$review->id]);
    }

    public function test_pending_draft_review_is_not_attributed_as_completed_work(): void
    {
        [$project, $repository] = $this->repository();
        $member = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::MEMBER]);
        $this->account($member, '45', 'reviewer');
        $review = $this->review($repository, '45', 'reviewer');
        $review->forceFill(['state' => 'pending', 'submitted_at' => null])->save();
        $this->assertNull(app(RepositoryChangeRequestReviewResolver::class)
            ->resolve($repository, collect([$review]))[$review->id]);
    }

    public function test_refuses_to_resolve_review_from_another_repository(): void
    {
        [, $repository] = $this->repository();
        [, $other] = $this->repository();
        $review = $this->review($other, '45', 'other');
        $this->expectException(RuntimeException::class);
        app(RepositoryChangeRequestReviewResolver::class)->resolve($repository, collect([$review]));
    }

    private function repository(): array
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => (string) (100 + $project->id),
            'account_login' => 'example', 'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $connection->id,
            'external_repository_id' => (string) (200 + $project->id),
            'full_name' => 'example/repo', 'default_branch' => 'main',
            'start_sha' => str_repeat('a', 40), 'last_synced_sha' => str_repeat('a', 40),
            'connected_at' => now(),
        ]);
        return [$project, $repository];
    }

    private function account(User $user, string $externalId, string $login): void
    {
        UserVcsAccount::query()->create([
            'user_id' => $user->id, 'vcs_instance_id' => GitHubInstance::get()->id,
            'external_user_id' => $externalId, 'login' => $login, 'connected_at' => now(),
        ]);
    }

    private function review(RepositoryConnection $repository, string $externalId, string $login, int $reviewId = 101): RepositoryChangeRequestReview
    {
        $pull = $repository->changeRequests()->create([
            'external_change_request_id' => (string) (3000 + $reviewId),
            'number' => $reviewId, 'title' => 'Review',
            'web_url' => 'https://github.com/example/repo/pull/'.$reviewId, 'state' => 'open',
        ]);
        return $pull->reviews()->create([
            'external_review_id' => (string) $reviewId,
            'reviewer_external_user_id' => $externalId,
            'reviewer_login' => $login,
            'state' => 'approved', 'submitted_at' => now(),
        ]);
    }
}
