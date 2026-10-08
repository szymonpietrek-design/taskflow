<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_workspace_and_assigns_the_creator_as_owner(): void
    {
        $user = User::factory()->create();

        $workspace = app(CreateWorkspace::class)->handle($user, 'Acme Team');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Acme Team',
            'slug' => 'acme-team',
        ]);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::OWNER->value,
        ]);
    }

    public function test_it_generates_a_unique_slug_for_workspaces_with_the_same_name(): void
    {
        $user = User::factory()->create();
        $action = app(CreateWorkspace::class);

        $first = $action->handle($user, 'Acme Team');
        $second = $action->handle($user, 'Acme Team');

        $this->assertSame('acme-team', $first->slug);
        $this->assertSame('acme-team-2', $second->slug);
    }

    public function test_it_rejects_an_empty_workspace_name(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(CreateWorkspace::class)->handle($user, '   ');
    }
}
