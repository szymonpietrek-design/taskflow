<?php

namespace Tests\Feature\Releases;

use App\Actions\Releases\ChangeReleaseStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Release;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReleaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_draft_can_be_planned_with_an_optional_date(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::PLANNED->value,
            'planned_for' => '2026-12-15',
        ])->assertSessionHasNoErrors()->assertRedirect(route('releases.show', [$project, $release]));

        $release->refresh();
        $this->assertSame(ReleaseStatus::PLANNED, $release->status);
        $this->assertSame('2026-12-15', $release->planned_for->format('Y-m-d'));
        $this->assertNull($release->released_at);
    }

    public function test_draft_can_be_planned_without_a_date(): void
    {
        [$owner, , $release] = $this->fixtures();
        app(ChangeReleaseStatus::class)->handle($owner, $release, ReleaseStatus::PLANNED);
        $this->assertSame(ReleaseStatus::PLANNED, $release->fresh()->status);
        $this->assertNull($release->fresh()->planned_for);
    }

    public function test_planned_release_can_be_published_and_gets_a_timestamp(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $release->update(['status' => ReleaseStatus::PLANNED]);
        $this->freezeTime();

        $this->actingAs($owner)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(ReleaseStatus::RELEASED, $release->fresh()->status);
        $this->assertSame(now()->toDateTimeString(), $release->fresh()->released_at->toDateTimeString());
    }

    public function test_draft_cannot_be_published_without_being_planned(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::RELEASED->value,
        ])->assertSessionHasErrors(['status']);

        $this->assertSame(ReleaseStatus::DRAFT, $release->fresh()->status);
        $this->assertNull($release->fresh()->released_at);
    }

    public function test_draft_and_planned_releases_can_be_canceled(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::CANCELED->value,
        ])->assertSessionHasNoErrors();
        $this->assertSame(ReleaseStatus::CANCELED, $release->fresh()->status);
        $this->assertNull($release->fresh()->released_at);

        $second = Release::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status' => ReleaseStatus::PLANNED,
        ]);
        app(ChangeReleaseStatus::class)->handle($owner, $second, ReleaseStatus::CANCELED);
        $this->assertSame(ReleaseStatus::CANCELED, $second->fresh()->status);
    }

    public function test_released_and_canceled_releases_cannot_be_reopened_or_changed(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        foreach ([ReleaseStatus::RELEASED, ReleaseStatus::CANCELED] as $closed) {
            $release->forceFill([
                'status' => $closed,
                'released_at' => $closed === ReleaseStatus::RELEASED ? now() : null,
            ])->save();
            foreach ([ReleaseStatus::PLANNED, ReleaseStatus::RELEASED, ReleaseStatus::CANCELED] as $target) {
                $this->actingAs($owner)->patch($this->url($project, $release), [
                    'status' => $target->value,
                ])->assertSessionHasErrors(['status']);
                $this->assertSame($closed, $release->fresh()->status);
            }
        }
    }

    public function test_same_status_and_invalid_targets_do_not_change_release(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        foreach ([ReleaseStatus::DRAFT->value, 99, 'released'] as $status) {
            $this->actingAs($owner)->patch($this->url($project, $release), [
                'status' => $status,
            ])->assertSessionHasErrors(['status']);
        }
        $this->assertSame(ReleaseStatus::DRAFT, $release->fresh()->status);
    }

    public function test_invalid_planned_date_is_rejected(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::PLANNED->value,
            'planned_for' => '2026-99-99',
        ])->assertSessionHasErrors(['planned_for']);
        $this->assertSame(ReleaseStatus::DRAFT, $release->fresh()->status);
    }

    public function test_reader_can_view_but_cannot_change_status(): void
    {
        [, $project, $release] = $this->fixtures();
        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($reader)->get(route('releases.show', [$project, $release]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('can_manage_release', false));
        $this->actingAs($reader)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::PLANNED->value,
        ])->assertForbidden();
    }

    public function test_project_manager_can_change_status(): void
    {
        [, $project, $release] = $this->fixtures();
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

        $this->actingAs($manager)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::PLANNED->value,
        ])->assertSessionHasNoErrors();
        $this->assertSame(ReleaseStatus::PLANNED, $release->fresh()->status);
    }

    public function test_foreign_release_cannot_be_changed_through_another_project(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $this->actingAs($owner)->patch($this->url($other, $release), [
            'status' => ReleaseStatus::PLANNED->value,
        ])->assertNotFound();
        $this->assertSame(ReleaseStatus::DRAFT, $release->fresh()->status);
    }

    public function test_archived_and_standard_projects_cannot_change_release_status(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->component('releases/Show')
                ->where('can_manage_release', false));
        $this->actingAs($owner)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::PLANNED->value,
        ])->assertSessionHasErrors(['status']);

        $project->update(['archived_at' => null, 'type' => ProjectType::STANDARD]);
        $this->actingAs($owner)->patch($this->url($project, $release), [
            'status' => ReleaseStatus::PLANNED->value,
        ])->assertNotFound();
    }

    public function test_status_change_does_not_modify_task_assignments(): void
    {
        [$owner, , $release] = $this->fixtures();
        $existing = $release->tasks()->count();
        app(ChangeReleaseStatus::class)->handle($owner, $release, ReleaseStatus::PLANNED);
        app(ChangeReleaseStatus::class)->handle($owner, $release->fresh(), ReleaseStatus::RELEASED);
        $this->assertSame($existing, $release->tasks()->count());
        $this->assertSame(0, \App\Models\TaskActivity::query()->count());
    }

    public function test_publishing_release_locks_its_task_membership(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $status = TaskStatus::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status_id' => $status->id,
            'release_id' => $release->id,
        ]);
        app(ChangeReleaseStatus::class)->handle($owner, $release, ReleaseStatus::PLANNED);
        app(ChangeReleaseStatus::class)->handle($owner, $release->fresh(), ReleaseStatus::RELEASED);

        $this->actingAs($owner)->delete(route('releases.tasks.destroy', [$project, $release, $task]))
            ->assertSessionHasErrors(['release']);
        $this->assertSame($release->id, $task->fresh()->release_id);
    }

    /** @return array{User, Project, Release} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $release = Release::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status' => ReleaseStatus::DRAFT,
        ]);

        return [$owner, $project, $release];
    }

    private function url(Project $project, Release $release): string
    {
        return route('releases.status.update', [$project, $release]);
    }
}
