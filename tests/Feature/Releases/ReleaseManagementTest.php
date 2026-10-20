<?php

namespace Tests\Feature\Releases;

use App\Actions\Releases\CreateRelease;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Release;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReleaseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_view_and_create_a_draft_release(): void
    {
        [$owner, $project] = $this->application();

        $this->actingAs($owner)->get(route('releases.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('releases/Index')
                ->where('can_create', true)->has('releases.data', 0));

        $this->actingAs($owner)->post(route('releases.store', $project), [
            'version' => '1.2.3',
            'name' => ' Autumn release ',
            'description' => 'First public version',
            'planned_for' => '2026-11-12',
        ])->assertSessionHasNoErrors();

        $release = Release::query()->sole();
        $this->assertSame('1.2.3', $release->version);
        $this->assertSame('Autumn release', $release->name);
        $this->assertSame('2026-11-12', $release->planned_for->format('Y-m-d'));
        $this->assertNull($release->released_at);

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('releases/Show')
            ->where('release.version', '1.2.3')->where('release.status', 'draft'));
    }

    public function test_releases_are_sorted_by_numeric_version_components(): void
    {
        [$owner, $project] = $this->application();
        foreach (['1.2.0', '1.10.0', '2.0.0'] as $version) {
            app(CreateRelease::class)->handle($owner, $project, $version);
        }

        $this->actingAs($owner)->get(route('releases.index', $project))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Index')
                ->where('releases.data.0.version', '2.0.0')
                ->where('releases.data.1.version', '1.10.0')
                ->where('releases.data.2.version', '1.2.0'));
    }

    public function test_version_validation_and_duplicate_are_reported_as_form_errors(): void
    {
        [$owner, $project] = $this->application();
        app(CreateRelease::class)->handle($owner, $project, '1.0.0');

        $this->actingAs($owner)->post(route('releases.store', $project), ['version' => '01.1.0'])
            ->assertSessionHasErrors(['version']);
        $this->actingAs($owner)->post(route('releases.store', $project), ['version' => '1.0.0'])
            ->assertSessionHasErrors(['version']);
        $this->actingAs($owner)->post(route('releases.store', $project), ['version' => '4294967296.0.0'])
            ->assertSessionHasErrors(['version']);
        $this->assertDatabaseCount('releases', 1);
    }

    public function test_project_member_can_view_but_cannot_create(): void
    {
        [, $project] = $this->application();
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($member)->get(route('releases.index', $project))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('releases/Index')->where('can_create', false));
        $this->actingAs($member)->post(route('releases.store', $project), ['version' => '1.0.0'])->assertForbidden();
        $this->assertDatabaseCount('releases', 0);
    }

    public function test_project_manager_can_create(): void
    {
        [, $project] = $this->application();
        $manager = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $manager->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => ProjectRole::MANAGER,
        ]);

        $this->actingAs($manager)->post(route('releases.store', $project), ['version' => '1.0.0'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('releases', 1);
    }

    public function test_non_application_and_foreign_release_are_not_accessible(): void
    {
        [$owner, $project] = $this->application();
        $standard = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::STANDARD,
        ]);
        $this->actingAs($owner)->get(route('releases.index', $standard))->assertNotFound();
        $this->actingAs($owner)->post(route('releases.store', $standard), ['version' => '1.0.0'])->assertNotFound();

        $second = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $foreign = Release::factory()->create(['project_id' => $second->id]);
        $this->actingAs($owner)->get(route('releases.show', [$project, $foreign]))->assertNotFound();
    }

    public function test_private_project_is_not_visible_to_unassigned_user(): void
    {
        [, $project] = $this->application();
        $project->update(['visibility' => ProjectVisibility::PRIVATE]);
        $member = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $this->actingAs($member)->get(route('releases.index', $project))->assertForbidden();
    }

    public function test_archived_application_remains_readable_but_cannot_create(): void
    {
        [$owner, $project] = $this->application();
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->get(route('releases.index', $project))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('releases/Index')->where('can_create', false));
        $this->actingAs($owner)->post(route('releases.store', $project), ['version' => '1.0.0'])
            ->assertForbidden();
    }

    public function test_guests_must_log_in(): void
    {
        [, $project] = $this->application();
        $this->get(route('releases.index', $project))->assertRedirect(route('login'));
    }

    /** @return array{User, Project} */
    private function application(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id, 'created_by' => $owner->id, 'type' => ProjectType::APPLICATION,
        ]);

        return [$owner, $project];
    }
}
