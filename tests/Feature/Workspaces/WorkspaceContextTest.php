<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_workspace_is_shared_on_authenticated_inertia_pages(): void
    {
        $user = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($user, 'Alpha Team');
        $second = app(CreateWorkspace::class)->handle($user, 'Beta Team');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $second->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('workspace.items', 2)
                ->where('workspace.current.id', $second->id)
                ->where('workspace.current.name', 'Beta Team')
                ->where('workspace.items.0.id', $first->id)
                ->where('workspace.items.1.id', $second->id),
            );
    }

    public function test_invalid_current_workspace_falls_back_to_first_available_workspace(): void
    {
        $user = User::factory()->create();
        $first = app(CreateWorkspace::class)->handle($user, 'Alpha Team');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => 999999])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('current_workspace_id', $first->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current.id', $first->id),
            );
    }

    public function test_authenticated_user_without_workspaces_receives_an_empty_workspace_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current', null)
                ->has('workspace.items', 0),
            );
    }
}
