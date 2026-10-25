<?php

namespace Tests\Feature\Performance;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Projects\ProjectVisibilityQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProjectVisibilityQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_visibility_agrees_with_project_policy_for_active_archived_and_suspended_memberships(): void
    {
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $active = Project::factory()->create(['workspace_id' => $workspace->id]);
        $archived = Project::factory()->create(['workspace_id' => $workspace->id, 'archived_at' => now()]);
        $foreign = Project::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $owner = User::factory()->create();
        WorkspaceMember::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        $projectMember = User::factory()->create();
        foreach ([$active, $archived] as $project) {
            ProjectMember::factory()->create([
                'user_id' => $projectMember->id,
                'project_id' => $project->id,
                'role' => ProjectRole::MEMBER,
            ]);
        }

        $manager = User::factory()->create();
        ProjectMember::factory()->create([
            'user_id' => $manager->id,
            'project_id' => $archived->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $suspendedAdmin = User::factory()->create();
        WorkspaceMember::factory()->create([
            'user_id' => $suspendedAdmin->id,
            'workspace_id' => $workspace->id,
            'role' => WorkspaceRole::ADMIN,
            'suspended_at' => now(),
        ]);
        ProjectMember::factory()->create([
            'user_id' => $suspendedAdmin->id,
            'project_id' => $active->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $outsider = User::factory()->create();

        foreach ([$owner, $projectMember, $manager, $suspendedAdmin, $outsider] as $user) {
            foreach ([$active, $archived, $foreign] as $project) {
                $fromPolicy = Gate::forUser($user)->allows('view', $project);
                $fromQuery = ProjectVisibilityQuery::visibleTo(
                    Project::query()->whereKey($project->id),
                    $user->id,
                )->exists();

                $this->assertSame(
                    $fromPolicy,
                    $fromQuery,
                    "Policy/query mismatch for user {$user->id}, project {$project->id}",
                );
            }
        }
    }
}
