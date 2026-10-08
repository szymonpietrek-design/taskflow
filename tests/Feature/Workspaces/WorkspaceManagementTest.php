<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
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
                ->has('workspaces', 2)
                ->where('workspaces.0.name', 'Alpha Team')
                ->where('workspaces.0.role', 'owner')
                ->where('workspaces.0.is_current', true)
                ->where('workspaces.1.name', 'Beta Team')
                ->where('workspaces.1.is_current', false),
            );
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
