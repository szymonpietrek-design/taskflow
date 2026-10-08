<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\WorkspaceRole;
use App\Models\TaskMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_messages_are_stored_separately_from_system_activity(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);

        $project = app(CreateProject::class)->handle($owner, $workspace, 'Task Project', 'APP');
        $task = app(CreateTask::class)->handle($owner, $project, 'Discuss implementation');

        $message = TaskMessage::factory()->create([
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'body' => 'The client asked for a shorter expiration time.',
        ]);

        $this->assertSame(1, $task->messages()->count());
        $this->assertSame($message->id, $task->messages()->firstOrFail()->id);
        $this->assertSame(0, $task->activities()->count());
        $this->assertSame($task->id, $message->task->id);
        $this->assertSame($owner->id, $message->author->id);
    }
}
