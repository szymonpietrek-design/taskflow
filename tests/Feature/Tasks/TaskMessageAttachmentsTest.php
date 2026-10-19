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

    public function test_editing_message_can_link_an_inline_uploaded_image_and_render_it(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner);
        $this->post(route('tasks.messages.store', [$project, $task]), ['body' => 'Initial message'])
            ->assertSessionHasNoErrors();
        $message = $task->messages()->sole();

        // The same private endpoint is used by the task description and messages.
        $response = $this->post(route('tasks.attachments.inline', [$project, $task]), [
            'file' => UploadedFile::fake()->createWithContent('inline.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1cAAAAASUVORK5CYII=')),
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('attachment.id');
        $attachment = $task->attachments()->findOrFail($id);

        $body = "Edited with image\n\n![inline.png](attachment:{$id})";
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => $body,
            'attachment_ids' => [$id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($body, $message->fresh()->body);
        $this->assertSame([$id], $message->attachments()->pluck('task_attachments.id')->all());
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.body_html', fn (string $html): bool =>
                    str_contains($html, '<img ')
                    && str_contains($html, route('tasks.attachments.preview', [$project, $task, $attachment], false))));
    }

    public function test_editing_message_adds_task_file_without_unlinking_existing_files(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner);
        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('before.pdf', 2, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $first = $task->attachments()->sole();
        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Old text', 'attachment_ids' => [$first->id],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();

        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('after.pdf', 2, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $second = $task->attachments()->latest('id')->firstOrFail();
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Updated text', 'attachment_ids' => [$second->id],
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$first->id, $second->id],
            $message->attachments()->pluck('task_attachments.id')->all());

        // An ordinary text-only edit also preserves both links.
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Another edit',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $message->attachments()->count());
    }

    public function test_editing_cannot_unlink_an_image_still_referenced_in_message_body(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner);
        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1cAAAAASUVORK5CYII='))],
        ])->assertSessionHasNoErrors();
        $attachment = $task->attachments()->sole();
        $body = "Screenshot\n\n![photo](attachment:{$attachment->id})";
        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => $body,
            'attachment_ids' => [$attachment->id],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();

        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => $body,
            'sync_attachments' => '1',
        ])->assertSessionHasErrors('attachment_ids');
        $this->assertSame($body, $message->fresh()->body);
        $this->assertSame(1, $message->attachments()->count());

        // The old direct unlink route is protected as well.
        $this->delete(route('tasks.messages.attachments.destroy', [$project, $task, $message, $attachment]))
            ->assertSessionHasErrors('attachment');
        $this->assertSame(1, $message->attachments()->count());

        // Remove the inline reference first; now detaching is safe.
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Screenshot removed',
            'sync_attachments' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $message->attachments()->count());
        $this->assertSame(1, $task->attachments()->count());
    }

    public function test_editing_message_can_uncheck_attached_files_without_deleting_task_files(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner);
        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [
                UploadedFile::fake()->create('first.pdf', 2, 'application/pdf'),
                UploadedFile::fake()->create('second.pdf', 2, 'application/pdf'),
                UploadedFile::fake()->create('third.pdf', 2, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();
        $files = $task->attachments()->orderBy('id')->get();

        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Original', 'attachment_ids' => [$files[0]->id, $files[1]->id],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();

        // The edit form sends the entire selection; first is unchecked,
        // third is checked, and second stays checked.
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Edited',
            'sync_attachments' => '1',
            'attachment_ids' => [$files[1]->id, $files[2]->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Edited', $message->fresh()->body);
        $this->assertEqualsCanonicalizing([$files[1]->id, $files[2]->id],
            $message->attachments()->pluck('task_attachments.id')->all());

        // No IDs in a sync request means the user unchecked all files.
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Edited again', 'sync_attachments' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $message->attachments()->count());
        $this->assertSame(3, $task->attachments()->count());
        foreach ($files as $file) {
            Storage::disk('local')->assertExists($file->path);
        }
    }

    public function test_replacing_last_file_of_full_message_is_allowed_but_adding_21st_is_rejected(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner);
        $files = [];
        for ($i = 0; $i < 21; $i++) {
            $files[] = $task->attachments()->create([
                'uploaded_by' => $owner->id,
                'original_name' => "file-{$i}.txt",
                'path' => "task-attachments/{$task->id}/file-{$i}.txt",
                'mime_type' => 'text/plain',
                'size' => 5,
            ]);
        }
        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Original',
            'attachment_ids' => array_map(fn ($file) => $file->id, array_slice($files, 0, 20)),
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();
        $route = route('tasks.messages.update', [$project, $task, $message]);

        // Replacing (not adding) one linked file should not exceed the limit.
        // IDs 1 through 20 replace the original 0 through 19: exactly 20 distinct files.
        $selection = array_map(fn ($file) => $file->id, array_slice($files, 1, 20));
        $this->patch($route, [
            'body' => 'Replaced', 'sync_attachments' => '1', 'attachment_ids' => $selection,
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing($selection, $message->attachments()->pluck('task_attachments.id')->all());

        $this->patch($route, [
            'body' => 'Invalid', 'sync_attachments' => '1',
            'attachment_ids' => array_map(fn ($file) => $file->id, $files),
        ])->assertSessionHasErrors('attachment_ids');
        $this->assertSame('Replaced', $message->fresh()->body);
        $this->assertEqualsCanonicalizing($selection, $message->attachments()->pluck('task_attachments.id')->all());
    }

    public function test_editing_message_cannot_link_file_from_another_task_or_exceed_limit(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner);
        $otherTask = app(CreateTask::class)->handle($owner, $project, 'Another task');
        $this->post(route('tasks.attachments.store', [$project, $otherTask]), [
            'files' => [UploadedFile::fake()->createWithContent('other.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1cAAAAASUVORK5CYII='))],
        ])->assertSessionHasNoErrors();
        $wrongId = $otherTask->attachments()->sole()->id;
        $this->post(route('tasks.messages.store', [$project, $task]), ['body' => 'Original'])
            ->assertSessionHasNoErrors();
        $message = $task->messages()->sole();

        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Changed', 'attachment_ids' => [$wrongId],
        ])->assertSessionHasErrors('attachment_ids.0');
        $this->assertSame('Original', $message->fresh()->body);
        $this->assertSame(0, $message->attachments()->count());

        $files = [];
        for ($i = 0; $i < 21; $i++) {
            $files[] = $task->attachments()->create([
                'uploaded_by' => $owner->id,
                'original_name' => "file-{$i}.txt",
                'path' => "task-attachments/{$task->id}/file-{$i}.txt",
                'mime_type' => 'text/plain',
                'size' => 5,
            ]);
        }
        $message->attachments()->sync(array_map(fn ($file) => $file->id, array_slice($files, 0, 20)));
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Changed again', 'attachment_ids' => [$files[20]->id],
        ])->assertSessionHasErrors('attachment_ids');
        $this->assertSame('Original', $message->fresh()->body);
        $this->assertSame(20, $message->attachments()->count());
    }

    public function test_editing_message_with_inline_files_respects_message_authorization(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner);
        $this->post(route('tasks.messages.store', [$project, $task]), ['body' => 'Owner'])
            ->assertSessionHasNoErrors();
        $message = $task->messages()->sole();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Unauthorized', 'attachment_ids' => [],
        ])->assertForbidden();

        $this->actingAs($owner);
        $project->forceFill(['archived_at' => now()])->save();
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Archived', 'attachment_ids' => [],
        ])->assertForbidden();
        $this->assertSame('Owner', $message->fresh()->body);
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
