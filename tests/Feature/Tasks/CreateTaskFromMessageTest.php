<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\CreateTaskFromMessage;
use App\Enums\ProjectVisibility;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
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

class CreateTaskFromMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_contributor_sees_prefilled_form_with_source_message(): void
    {
        [$project, $owner, $source, $message] = $this->source();

        $this->actingAs($owner)
            ->get(route('tasks.messages.create-task', [$project, $source, $message]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/CreateFromMessage')
                ->where('project.key', 'APP')
                ->where('source_task.id', $source->id)
                ->where('message.id', $message->id)
                ->where('suggested_title', 'Add notification after signup')
                ->has('statuses', 4)
                ->has('assignees', 1));
    }

    public function test_creation_from_message_is_independent_and_keeps_source_links(): void
    {
        [$project, $owner, $source, $message] = $this->source();

        $this->actingAs($owner)
            ->post(route('tasks.messages.store-task', [$project, $source, $message]), [
                'title' => 'Send welcome email',
                'description' => "Add email verification after signup.\nInclude a secure token.",
                'type' => TaskType::FEATURE->value,
                'priority' => TaskPriority::HIGH->value,
                'assignee_id' => $owner->id,
            ])->assertSessionHasNoErrors();

        $created = $project->tasks()->where('source_message_id', $message->id)->sole();
        $this->assertSame('APP-2', $created->reference());
        $this->assertNull($created->parent_id);
        $this->assertSame($source->id, $created->source_task_id);
        $this->assertSame($message->id, $created->source_message_id);
        $this->assertSame($owner->id, $created->assignee_id);
        $this->assertSame(TaskType::FEATURE, $created->type);
        $this->assertSame(TaskPriority::HIGH, $created->priority);
        $this->assertSame('Send welcome email', $created->title);
        $this->assertSame(2, $project->tasks()->count());

        $this->actingAs($owner)
            ->get(route('tasks.show', [$project, $created]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('task.source_task.id', $source->id)
                ->where('task.source_task.reference', 'APP-1')
                ->where('task.source_task.message_id', $message->id));
    }

    public function test_can_create_from_a_message_in_an_existing_subtask_without_creating_a_grandchild(): void
    {
        [$project, $owner, $source] = $this->source();
        $subtask = app(CreateTask::class)->handle($owner, $project, 'Fix registration', parent: $source);
        $message = $subtask->messages()->create(['user_id' => $owner->id, 'body' => 'Add integration test']);

        $created = app(CreateTaskFromMessage::class)->handle($owner, $project, $subtask, $message, 'Integration test');

        $this->assertNull($created->parent_id);
        $this->assertSame($subtask->id, $created->source_task_id);
        $this->assertSame($message->id, $created->source_message_id);
    }

    public function test_reader_cannot_open_form_or_create_task_but_can_see_message_without_button(): void
    {
        [$project, $owner, $source, $message] = $this->source();
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($viewer)->get(route('tasks.show', [$project, $source]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('messages.0.can_create_task', false));

        $this->actingAs($viewer)->get(route('tasks.messages.create-task', [$project, $source, $message]))
            ->assertForbidden();
        $this->actingAs($viewer)->post(route('tasks.messages.store-task', [$project, $source, $message]), $this->payload())
            ->assertForbidden();

        $this->assertSame(1, $project->tasks()->count());
    }

    public function test_foreign_task_or_message_cannot_be_used_as_source(): void
    {
        [$project, $owner, $source, $message] = $this->source();
        [$otherProject, , $otherTask, $otherMessage] = $this->source('OTHER');

        $this->actingAs($owner)
            ->get(route('tasks.messages.create-task', [$project, $source, $otherMessage]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('tasks.messages.store-task', [$project, $source, $otherMessage]), $this->payload())
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('tasks.messages.store-task', [$otherProject, $source, $message]), $this->payload())
            ->assertNotFound();

        $this->assertSame(1, $project->tasks()->count());
        $this->assertSame(1, $otherProject->tasks()->count());
    }

    public function test_source_from_private_project_is_not_accessible_to_unauthorized_user(): void
    {
        [$project, $owner, $source, $message] = $this->source();
        $project->forceFill(['visibility' => ProjectVisibility::PRIVATE])->save();
        $outsider = User::factory()->create();
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($outsider)->get(route('tasks.messages.create-task', [$project, $source, $message]))
            ->assertForbidden();
        $this->actingAs($viewer)->post(route('tasks.messages.store-task', [$project, $source, $message]), $this->payload())
            ->assertForbidden();
    }

    public function test_forged_parent_id_and_invalid_data_are_rejected(): void
    {
        [$project, $owner, $source, $message] = $this->source();

        $this->actingAs($owner)
            ->post(route('tasks.messages.store-task', [$project, $source, $message]), $this->payload() + [
                'parent_id' => $source->id,
            ])
            ->assertSessionHasErrors(['parent_id']);

        $this->actingAs($owner)
            ->post(route('tasks.messages.store-task', [$project, $source, $message]), [
                'title' => '', 'type' => 999, 'priority' => 999,
            ])->assertSessionHasErrors(['title', 'type', 'priority']);

        $this->assertSame(1, $project->tasks()->count());
    }

    public function test_deleted_source_message_does_not_delete_independent_task(): void
    {
        [$project, $owner, $source, $message] = $this->source();
        $created = app(CreateTaskFromMessage::class)->handle($owner, $project, $source, $message, 'Preserved task', description: $message->body);

        $message->delete();
        $created->refresh();

        $this->assertNull($created->source_message_id);
        $this->assertSame($source->id, $created->source_task_id);
        $this->assertSame('Preserved task', $created->title);
        $this->assertSame($message->body, $created->description);

        $this->actingAs($owner)->get(route('tasks.show', [$project, $created]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('task.source_task.id', $source->id)
                ->where('task.source_task.message_id', null));
    }

    /** @return array{Project,User,Task,TaskMessage} */
    private function source(string $key = 'APP'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Project '.$key, $key);
        $task = app(CreateTask::class)->handle($owner, $project, 'Registration');
        $message = $task->messages()->create([
            'user_id' => $owner->id,
            'body' => "Add notification after signup\nEmail should include a confirmation link.",
        ]);

        return [$project, $owner, $task, $message];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'title' => 'New task',
            'type' => TaskType::TASK->value,
            'priority' => TaskPriority::NORMAL->value,
        ];
    }
}
