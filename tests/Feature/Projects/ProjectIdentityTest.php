<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_project_with_selected_icon_and_color(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Studio');

        $this->actingAs($owner)
            ->post(route('workspaces.projects.store', $workspace), [
                'name' => 'Storefront', 'key' => 'SHOP', 'type' => ProjectType::STANDARD->value,
                'visibility' => 1, 'icon' => 'shopping-bag', 'color' => 'emerald',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'workspace_id' => $workspace->id, 'key' => 'SHOP',
            'icon' => 'shopping-bag', 'color' => 'emerald',
        ]);
    }

    public function test_owner_can_edit_identity_without_changing_project_key_or_type(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Studio');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Platform', 'PLAT', ProjectType::APPLICATION);

        $this->actingAs($owner)
            ->patch(route('workspaces.projects.update', [$workspace, $project]), [
                'name' => 'Platform', 'visibility' => 1,
                'icon' => 'code-2', 'color' => 'blue',
                'key' => 'WRONG', 'type' => ProjectType::STANDARD->value,
            ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('code-2', $project->icon);
        $this->assertSame('blue', $project->color);
        $this->assertSame('PLAT', $project->key);
        $this->assertSame(ProjectType::APPLICATION, $project->type);

        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('project.icon', 'code-2')
                ->where('project.color', 'blue')
                ->etc());

        $this->actingAs($owner)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where("projectsByWorkspace.{$workspace->id}.0.icon", 'code-2')
                ->where("projectsByWorkspace.{$workspace->id}.0.color", 'blue')
                ->etc());

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('projects.items.0.icon', 'code-2')
                ->where('projects.items.0.color', 'blue')
                ->etc()
            );
    }

    public function test_owner_can_choose_an_extended_icon_and_it_is_returned_by_workspace_projects(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Studio');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Analytics', 'ANLT');

        $this->actingAs($owner)->patch(route('workspaces.projects.update', [$workspace, $project]), [
            'name' => 'Analytics', 'visibility' => 1,
            'icon' => 'chart-no-axes-combined', 'color' => 'cyan',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id, 'icon' => 'chart-no-axes-combined', 'color' => 'cyan',
        ]);

        $this->actingAs($owner)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where("projectsByWorkspace.{$workspace->id}.0.icon", 'chart-no-axes-combined')
                ->etc());
    }

    public function test_invalid_icon_and_color_are_rejected_without_changes(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Studio');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Platform', 'PLAT');

        $this->actingAs($owner)->patch(route('workspaces.projects.update', [$workspace, $project]), [
            'name' => 'Platform', 'visibility' => 1,
            'icon' => 'script', 'color' => 'javascript:red',
        ])->assertSessionHasErrors(['icon', 'color']);

        $this->assertNull($project->fresh()->icon);
        $this->assertNull($project->fresh()->color);

        $this->actingAs($owner)->post(route('workspaces.projects.store', $workspace), [
            'name' => 'Invalid', 'key' => 'INV', 'type' => 1, 'visibility' => 1,
            'icon' => 'custom-html', 'color' => '#000',
        ])->assertSessionHasErrors(['icon', 'color']);
        $this->assertDatabaseMissing('projects', ['workspace_id' => $workspace->id, 'key' => 'INV']);
    }

    public function test_legacy_requests_keep_existing_identity_and_unset_projects_use_fallback(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Studio');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Platform', 'PLAT');
        $this->assertNull($project->icon);
        $this->assertNull($project->color);

        $project->update(['icon' => 'rocket', 'color' => 'violet']);
        $this->actingAs($owner)->patch(route('workspaces.projects.update', [$workspace, $project]), [
            'name' => 'Platform Updated', 'visibility' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('rocket', $project->fresh()->icon);
        $this->assertSame('violet', $project->fresh()->color);
    }
}
