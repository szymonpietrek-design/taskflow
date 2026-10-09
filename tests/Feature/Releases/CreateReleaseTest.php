<?php

namespace Tests\Feature\Releases;

use App\Actions\Releases\CreateRelease;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Release;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_a_draft_release_with_semver_parts(): void
    {
        [$owner, $project] = $this->applicationFor(WorkspaceRole::OWNER);

        $release = app(CreateRelease::class)->handle(
            $owner, $project, '1.10.2', ' Autumn release ', ' First release notes ', now()->addWeek(),
        );

        $this->assertSame($project->id, $release->project_id);
        $this->assertSame($owner->id, $release->created_by);
        $this->assertSame('1.10.2', $release->version);
        $this->assertSame(1, $release->version_major);
        $this->assertSame(10, $release->version_minor);
        $this->assertSame(2, $release->version_patch);
        $this->assertSame(ReleaseStatus::DRAFT, $release->status);
        $this->assertSame('Autumn release', $release->name);
        $this->assertSame('First release notes', $release->description);
        $this->assertNotNull($release->planned_for);
        $this->assertNull($release->released_at);
        $this->assertSame($project->id, $release->project->id);
        $this->assertSame(1, $project->releases()->count());
    }

    public function test_workspace_admin_and_project_manager_can_create_releases(): void
    {
        [$admin, $adminProject] = $this->applicationFor(WorkspaceRole::ADMIN);
        $this->assertSame('1.0.0', app(CreateRelease::class)->handle($admin, $adminProject, '1.0.0')->version);

        [$manager, $managerProject] = $this->applicationFor(WorkspaceRole::MEMBER);
        ProjectMember::factory()->create([
            'project_id' => $managerProject->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $this->assertSame('1.0.0', app(CreateRelease::class)->handle($manager, $managerProject, '1.0.0')->version);
    }

    public function test_regular_project_member_cannot_create_releases(): void
    {
        [$member, $project] = $this->applicationFor(WorkspaceRole::MEMBER);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->expectException(AuthorizationException::class);
        app(CreateRelease::class)->handle($member, $project, '1.0.0');
    }

    public function test_user_outside_workspace_cannot_create_releases(): void
    {
        [, $project] = $this->applicationFor(WorkspaceRole::OWNER);
        $outsider = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(CreateRelease::class)->handle($outsider, $project, '1.0.0');
    }

    public function test_standard_projects_cannot_have_releases(): void
    {
        [$user, $project] = $this->applicationFor(WorkspaceRole::OWNER);
        $project->update(['type' => ProjectType::STANDARD]);

        $this->expectException(InvalidArgumentException::class);
        app(CreateRelease::class)->handle($user, $project, '1.0.0');
    }

    public function test_archived_application_cannot_have_new_releases(): void
    {
        [$user, $project] = $this->applicationFor(WorkspaceRole::OWNER);
        $project->update(['archived_at' => now()]);

        $this->expectException(InvalidArgumentException::class);
        app(CreateRelease::class)->handle($user, $project, '1.0.0');
    }

    public function test_version_must_be_unique_within_one_project(): void
    {
        [$owner, $project] = $this->applicationFor(WorkspaceRole::OWNER);
        app(CreateRelease::class)->handle($owner, $project, '1.0.0');

        $this->expectException(InvalidArgumentException::class);
        app(CreateRelease::class)->handle($owner, $project, '1.0.0');
    }

    public function test_same_version_is_allowed_in_different_projects(): void
    {
        [$owner, $first] = $this->applicationFor(WorkspaceRole::OWNER);
        $second = Project::factory()->create([
            'workspace_id' => $first->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);

        $this->assertSame('1.0.0', app(CreateRelease::class)->handle($owner, $first, '1.0.0')->version);
        $this->assertSame('1.0.0', app(CreateRelease::class)->handle($owner, $second, '1.0.0')->version);
        $this->assertSame(2, Release::query()->count());
    }

    public function test_invalid_version_is_rejected_without_creating_a_release(): void
    {
        [$owner, $project] = $this->applicationFor(WorkspaceRole::OWNER);

        try {
            app(CreateRelease::class)->handle($owner, $project, '01.2.3');
            $this->fail('An invalid version must not be created.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('releases', 0);
        }
    }

    public function test_factory_creates_a_release_for_an_application(): void
    {
        $release = Release::factory()->create();

        $this->assertSame(ProjectType::APPLICATION, $release->project->type);
        $this->assertSame(ReleaseStatus::DRAFT, $release->status);
    }

    /** @return array{User, Project} */
    private function applicationFor(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $user->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => ProjectType::APPLICATION,
        ]);

        return [$user, $project];
    }
}
