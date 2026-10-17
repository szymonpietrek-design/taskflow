<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskMessageAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_message_can_upload_a_private_file_without_text(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)->post(route('tasks.messages.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('test-results.json', 10, 'application/json')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $message = $task->messages()->sole();
        $this->assertSame('', $message->body);
        $attachment = $message->attachments()->sole();
        $this->assertSame($task->id, $attachment->task_id);
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($owner)->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page->component('tasks/Show')
                ->has('attachments', 1)
                ->has('messages', 1)
                ->where('messages.0.attachments.0.id', $attachment->id));
    }

    public function test_existing_file_can_be_reused_in_multiple_messages_without_duplication(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner)->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('design.pdf', 3, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $attachment = $task->attachments()->sole();

        foreach (['First use', 'Second use'] as $body) {
            $this->post(route('tasks.messages.store', [$project, $task]), [
                'body' => $body, 'attachment_ids' => [$attachment->id],
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('task_attachments', 1);
        $this->assertDatabaseCount('task_message_attachments', 2);
        $this->assertSame(2, $attachment->messages()->count());

        $message = $task->messages()->oldest('id')->firstOrFail();
        $this->delete(route('tasks.messages.destroy', [$project, $task, $message]))->assertRedirect();
        $this->assertDatabaseCount('task_attachments', 1);
        $this->assertDatabaseCount('task_message_attachments', 1);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_other_task_attachment_cannot_be_linked(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $otherTask = app(CreateTask::class)->handle($owner, $project, 'Other task');
        $this->actingAs($owner)->post(route('tasks.attachments.store', [$project, $otherTask]), [
            'files' => [UploadedFile::fake()->create('private.pdf', 2, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $attachment = $otherTask->attachments()->sole();

        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Trying to attach another task file',
            'attachment_ids' => [$attachment->id],
        ])->assertSessionHasErrors('attachment_ids.0');
        $this->assertDatabaseCount('task_messages', 0);
    }

    public function test_linked_file_must_be_unlinked_before_it_can_be_deleted(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner)->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Test',
            'files' => [UploadedFile::fake()->create('spec.pdf', 2, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();
        $attachment = $task->attachments()->sole();

        $this->delete(route('tasks.attachments.destroy', [$project, $task, $attachment]))
            ->assertSessionHasErrors('attachment');
        Storage::disk('local')->assertExists($attachment->path);

        $this->delete(route('tasks.messages.attachments.destroy', [$project, $task, $message, $attachment]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('task_message_attachments', 0);
        $this->assertDatabaseCount('task_attachments', 1);

        $this->delete(route('tasks.attachments.destroy', [$project, $task, $attachment]))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_archived_tasks_and_outsiders_cannot_post_or_unlink_files(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner)->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Message',
            'files' => [UploadedFile::fake()->create('example.txt', 2, 'text/plain')],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();
        $attachment = $task->attachments()->sole();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->delete(route('tasks.messages.attachments.destroy', [$project, $task, $message, $attachment]))
            ->assertForbidden();
        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Outsider message', 'attachment_ids' => [$attachment->id],
        ])->assertForbidden();

        $this->actingAs($owner);
        $project->forceFill(['archived_at' => now()])->save();
        $this->post(route('tasks.messages.store', [$project, $task]), ['body' => 'Archived'])->assertForbidden();
        $this->delete(route('tasks.messages.attachments.destroy', [$project, $task, $message, $attachment]))
            ->assertForbidden();
        $this->get(route('tasks.attachments.download', [$project, $task, $attachment]))->assertOk();
    }

    /** @return array{\App\Models\Project, User, Task} */
    private function taskWithOwner(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Project', 'APP');
        $task = app(CreateTask::class)->handle($owner, $project, 'Message task');

        return [$project, $owner, $task];
    }
}
