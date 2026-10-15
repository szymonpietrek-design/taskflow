<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_listing_groups_projects_and_hides_other_peoples_private_projects(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $shared = app(CreateProject::class)->handle($owner, $workspace, 'Shared App', 'SHARED');
        $private = app(CreateProject::class)->handle(
            $owner, $workspace, 'Restricted App', 'HIDDEN',
            ProjectType::STANDARD, ProjectVisibility::PRIVATE,
        );
        $archived = app(CreateProject::class)->handle($owner, $workspace, 'Archived App', 'ARCH');
        $archived->update(['archived_at' => now()]);

        $this->actingAs($member)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Index')
                ->has("projectsByWorkspace.{$workspace->id}", 1)
                ->where("projectsByWorkspace.{$workspace->id}.0.id", $shared->id)
                ->where("projectsByWorkspace.{$workspace->id}.0.can_edit", false)
                ->etc());

        $this->actingAs($owner)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has("projectsByWorkspace.{$workspace->id}", 2)
                ->where("projectsByWorkspace.{$workspace->id}.1.id", $shared->id)
                ->etc());

        ProjectMember::factory()->create([
            'project_id' => $private->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MANAGER,
        ]);
        $this->actingAs($member)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has("projectsByWorkspace.{$workspace->id}", 2)
                ->where("projectsByWorkspace.{$workspace->id}.0.can_edit", true)
                ->etc());
    }

    public function test_creating_in_another_workspace_targets_the_url_not_the_current_session(): void
    {
        $owner = User::factory()->create();
        $current = app(CreateWorkspace::class)->handle($owner, 'Current');
        $target = app(CreateWorkspace::class)->handle($owner, 'Target');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $current->id])
            ->from(route('workspaces.index'))
            ->post(route('workspaces.projects.store', $target), [
                'name' => 'Customer App',
                'key' => 'app',
                'type' => ProjectType::APPLICATION->value,
                'visibility' => ProjectVisibility::WORKSPACE->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('current_workspace_id', $target->id)
            ->assertRedirect(route('workspaces.index'));

        $this->assertDatabaseHas('projects', [
            'workspace_id' => $target->id, 'key' => 'APP',
        ]);
        $this->assertDatabaseMissing('projects', [
            'workspace_id' => $current->id, 'key' => 'APP',
        ]);
    }

    public function test_member_and_outsider_cannot_create_projects_or_modify_another_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $project = app(CreateProject::class)->handle($owner, $workspace, 'App', 'APP');
        $payload = [
            'name' => 'Forbidden', 'key' => 'NOPE',
            'type' => 1, 'visibility' => 1,
        ];

        foreach ([$member, $outsider] as $user) {
            $this->actingAs($user)
                ->post(route('workspaces.projects.store', $workspace), $payload)
                ->assertForbidden();
            $this->actingAs($user)
                ->patch(route('workspaces.projects.update', [$workspace, $project]), [
                    'name' => 'Forbidden', 'visibility' => 1,
                ])->assertForbidden();
        }
        $this->assertDatabaseMissing('projects', ['name' => 'Forbidden']);
    }

    public function test_owner_can_update_details_without_changing_key_slug_or_type(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        $project = app(CreateProject::class)->handle(
            $owner, $workspace, 'Original App', 'APP', ProjectType::APPLICATION,
        );
        $slug = $project->slug;

        $this->actingAs($owner)
            ->from(route('workspaces.index'))
            ->patch(route('workspaces.projects.update', [$workspace, $project]), [
                'name' => 'Renamed App', 'description' => 'Updated description',
                'visibility' => ProjectVisibility::PRIVATE->value,
                'key' => 'CHANGED', 'type' => ProjectType::STANDARD->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workspaces.index'));

        $project->refresh();
        $this->assertSame('Renamed App', $project->name);
        $this->assertSame('Updated description', $project->description);
        $this->assertSame(ProjectVisibility::PRIVATE, $project->visibility);
        $this->assertSame('APP', $project->key);
        $this->assertSame($slug, $project->slug);
        $this->assertSame(ProjectType::APPLICATION, $project->type);
    }

    public function test_workspace_admin_and_project_manager_can_edit_but_member_cannot(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Team');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'App', 'APP');
        foreach ([[$admin, WorkspaceRole::ADMIN], [$manager, WorkspaceRole::MEMBER], [$member, WorkspaceRole::MEMBER]] as [$user, $role]) {
            WorkspaceMember::factory()->create([
                'workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role,
            ]);
        }
        ProjectMember::factory()->create([
            'project_id' => $project->id, 'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER,
        ]);
        foreach ([$admin, $manager] as $user) {
            $this->actingAs($user)
                ->patch(route('workspaces.projects.update', [$workspace, $project]), [
                    'name' => 'New Name', 'visibility' => 1,
                ])->assertRedirect();
        }
        $this->actingAs($member)
            ->patch(route('workspaces.projects.update', [$workspace, $project]), [
                'name' => 'No Permission', 'visibility' => 1,
            ])->assertForbidden();
    }

    public function test_project_routes_reject_invalid_fields_and_mismatched_workspaces(): void
    {
        $owner = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($owner, 'First');
        $second = app(CreateWorkspace::class)->handle($owner, 'Second');
        $project = app(CreateProject::class)->handle($owner, $first, 'Original', 'ORIG');

        $this->actingAs($owner)
            ->from(route('workspaces.index'))
            ->patch(route('workspaces.projects.update', [$first, $project]), [
                'name' => '', 'visibility' => 99,
            ])->assertSessionHasErrors(['name', 'visibility']);

        $this->actingAs($owner)
            ->patch(route('workspaces.projects.update', [$second, $project]), [
                'name' => 'Wrong Workspace', 'visibility' => 1,
            ])->assertForbidden();
        $this->assertSame('Original', $project->fresh()->name);
    }
}
