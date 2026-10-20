<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\ProjectRole;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskDiscussionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_task_shows_messages_in_order_with_authors_and_permissions(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $first = $task->messages()->create(['user_id' => $owner->id, 'body' => 'First update']);
        $second = $task->messages()->create(['user_id' => $owner->id, 'body' => 'Second update']);

        $this->actingAs($owner)
            ->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('can_post_message', true)
                ->has('messages', 2)
                ->where('messages.0.id', $first->id)
                ->where('messages.0.body', 'First update')
                ->where('messages.0.author.name', $owner->name)
                ->where('messages.0.can_update', true)
                ->where('messages.0.can_delete', true)
                ->where('messages.1.id', $second->id),
            );
    }

    public function test_authorized_contributor_can_post_a_message_without_creating_activity(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $member = $this->projectContributor($project);

        $this->actingAs($member)
            ->post(route('tasks.messages.store', [$project, $task]), ['body' => '  Investigating the issue.  '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', [$project, $task]));

        $message = $task->messages()->sole();
        $this->assertSame('Investigating the issue.', $message->body);
        $this->assertSame($member->id, $message->user_id);
        $this->assertSame(0, $task->activities()->count());
    }

    public function test_message_body_is_required_and_length_limited(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)
            ->post(route('tasks.messages.store', [$project, $task]), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->actingAs($owner)
            ->post(route('tasks.messages.store', [$project, $task]), ['body' => str_repeat('a', 10001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $task->messages()->count());
    }

    public function test_author_can_edit_and_delete_own_message(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $message = TaskMessage::factory()->create(['task_id' => $task->id, 'user_id' => $owner->id, 'body' => 'Draft']);

        $this->actingAs($owner)
            ->patch(route('tasks.messages.update', [$project, $task, $message]), ['body' => '  Reviewed  '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', [$project, $task]));

        $this->assertSame('Reviewed', $message->fresh()->body);

        $this->actingAs($owner)
            ->delete(route('tasks.messages.destroy', [$project, $task, $message]))
            ->assertRedirect(route('tasks.show', [$project, $task]));

        $this->assertDatabaseMissing('task_messages', ['id' => $message->id]);
        $this->assertSame(0, $task->activities()->count());
    }

    public function test_a_different_user_cannot_edit_or_delete_messages_even_if_workspace_owner(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $member = $this->projectContributor($project);
        $message = TaskMessage::factory()->create(['task_id' => $task->id, 'user_id' => $member->id, 'body' => 'Member opinion']);

        $this->actingAs($owner)
            ->patch(route('tasks.messages.update', [$project, $task, $message]), ['body' => 'Overwritten'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('tasks.messages.destroy', [$project, $task, $message]))
            ->assertForbidden();

        $this->assertSame('Member opinion', $message->fresh()->body);
    }

    public function test_unassigned_workspace_member_cannot_read_or_post_messages(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $viewer = $this->workspaceMember($project);
        $message = TaskMessage::factory()->create(['task_id' => $task->id, 'user_id' => $owner->id, 'body' => 'Visible context']);

        $this->actingAs($viewer)
            ->get(route('tasks.show', [$project, $task]))->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('tasks.messages.store', [$project, $task]), ['body' => 'Blocked'])
            ->assertForbidden();

        $this->assertSame(1, $task->messages()->count());
    }

    public function test_foreign_project_task_and_message_combinations_are_not_found(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        [$otherProject, , $otherTask] = $this->taskWithOwner('OTHER');
        $message = TaskMessage::factory()->create(['task_id' => $otherTask->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('tasks.messages.store', [$otherProject, $task]), ['body' => 'Blocked'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch(route('tasks.messages.update', [$project, $task, $message]), ['body' => 'Blocked'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('tasks.messages.destroy', [$project, $task, $message]))
            ->assertNotFound();

        $this->assertSame(0, $task->messages()->count());
    }

    public function test_private_project_non_member_and_outsider_cannot_access_discussion(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $project->forceFill(['visibility' => ProjectVisibility::PRIVATE])->save();
        $viewer = $this->workspaceMember($project);
        $stranger = User::factory()->create();

        foreach ([$viewer, $stranger] as $user) {
            $this->actingAs($user)
                ->get(route('tasks.show', [$project, $task]))
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('tasks.messages.store', [$project, $task]), ['body' => 'Blocked'])
                ->assertForbidden();
        }

        $this->assertSame(0, $task->messages()->count());
    }

    public function test_deleted_author_is_displayed_without_edit_permissions(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        TaskMessage::factory()->create(['task_id' => $task->id, 'user_id' => null, 'body' => 'Historical note']);

        $this->actingAs($owner)
            ->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('messages.0.author', null)
                ->where('messages.0.can_update', false)
                ->where('messages.0.can_delete', false),
            );
    }

    public function test_guest_must_log_in_before_posting(): void
    {
        [$project, , $task] = $this->taskWithOwner();

        $this->post(route('tasks.messages.store', [$project, $task]), ['body' => 'Anonymous'])
            ->assertRedirect(route('login'));
    }

    /** @return array{Project, User, Task} */
    private function taskWithOwner(string $key = 'APP'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        $project = app(CreateProject::class)->handle($owner, $workspace, 'Project '.$key, $key);
        $task = app(CreateTask::class)->handle($owner, $project, 'Discuss changes');

        return [$project, $owner, $task];
    }

    private function workspaceMember(Project $project): User
    {
        $user = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        return $user;
    }

    private function projectContributor(Project $project): User
    {
        $user = $this->workspaceMember($project);
        ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::MEMBER,
        ]);

        return $user;
    }
}
