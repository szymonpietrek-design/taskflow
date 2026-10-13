<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_page_lists_only_workspaces_available_to_the_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $alpha = app(CreateWorkspace::class)->handle($user, 'Alpha Team');
        app(CreateWorkspace::class)->handle($user, 'Beta Team');
        app(CreateWorkspace::class)->handle($otherUser, 'Private Team');

        $response = $this->actingAs($user)->get(route('workspaces.index'));

        $response
            ->assertOk()
            ->assertSessionHas('current_workspace_id', $alpha->id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Index')
                ->has('workspace.items', 2)
                ->where('workspace.current.id', $alpha->id)
                ->where('workspace.items.0.name', 'Alpha Team')
                ->where('workspace.items.0.role', 'owner')
                ->where('workspace.items.0.is_current', true)
                ->where('workspace.items.1.name', 'Beta Team')
                ->where('workspace.items.1.is_current', false),
            );
    }

    public function test_workspace_cards_preview_three_active_members_and_count_the_remainder(): void
    {
        $owner = User::factory()->create(['name' => 'Current Owner']);
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Alpha Team');
        $secondWorkspace = app(CreateWorkspace::class)->handle($owner, 'Beta Team');
        $outside = User::factory()->create(['name' => 'Outside Person']);
        app(CreateWorkspace::class)->handle($outside, 'Other Team');

        foreach (['Alice Example', 'Bob Example', 'Carol Example', 'Dave Example'] as $name) {
            $user = User::factory()->create(['name' => $name]);
            WorkspaceMember::factory()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::MEMBER,
            ]);
        }

        $suspended = User::factory()->create(['name' => 'Suspended Person']);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $suspended->id,
            'role' => WorkspaceRole::MEMBER,
            'suspended_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/Index')
                ->has("memberPreviews.{$workspace->id}.members", 3)
                ->where("memberPreviews.{$workspace->id}.remaining", 2)
                ->where("memberPreviews.{$workspace->id}.members.0.name", 'Current Owner')
                ->has("memberPreviews.{$secondWorkspace->id}.members", 1)
                ->where("memberPreviews.{$secondWorkspace->id}.remaining", 0)
                ->missing('memberPreviews.'.$outside->workspaces()->first()->id)
                ->etc());
    }

    public function test_user_can_create_a_workspace_from_the_inertia_flow(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workspaces.store'), [
            'name' => 'Product Team',
        ]);

        $workspace = $user->workspaces()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('current_workspace_id', $workspace->id)
            ->assertRedirect(route('workspaces.index'));

        $this->assertSame('Product Team', $workspace->name);
        $this->assertSame('product-team', $workspace->slug);
    }

    public function test_workspace_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('workspaces.index'))
            ->post(route('workspaces.store'), ['name' => '']);

        $response
            ->assertSessionHasErrors('name')
            ->assertRedirect(route('workspaces.index'));
    }

    public function test_member_can_switch_the_current_workspace(): void
    {
        $user = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($user, 'First Team');
        $second = app(CreateWorkspace::class)->handle($user, 'Second Team');

        $response = $this
            ->actingAs($user)
            ->withSession(['current_workspace_id' => $first->id])
            ->put(route('workspaces.current.update', $second));

        $response
            ->assertSessionHas('current_workspace_id', $second->id)
            ->assertRedirect();
    }

    public function test_user_cannot_switch_to_a_workspace_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($otherUser, 'Other Team');

        $this->actingAs($user)
            ->put(route('workspaces.current.update', $workspace))
            ->assertForbidden();
    }

    public function test_workspace_routes_require_authentication(): void
    {
        $this->get(route('workspaces.index'))
            ->assertRedirect(route('login'));
    }
}
