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

class TaskAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_owner_can_upload_and_list_private_attachments(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $file = UploadedFile::fake()->create('test-plan.pdf', 100, 'application/pdf');

        $this->actingAs($owner)
            ->post(route('tasks.attachments.store', [$project, $task]), ['files' => [$file]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $attachment = $task->attachments()->sole();
        $this->assertSame('test-plan.pdf', $attachment->original_name);
        $this->assertSame($owner->id, $attachment->uploaded_by);
        $this->assertStringStartsWith('task-attachments/'.$task->id.'/', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('public')->assertMissing($attachment->path);

        $this->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->has('attachments', 1)
                ->where('attachments.0.id', $attachment->id)
                ->where('attachments.0.name', 'test-plan.pdf')
                ->where('attachments.0.is_image', false));
    }

    public function test_download_requires_access_to_the_exact_project_task_and_attachment(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner)->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('documentation.pdf', 100, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $attachment = $task->attachments()->sole();

        $this->get(route('tasks.attachments.download', [$project, $task, $attachment]))
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff');

        $other = User::factory()->create();
        $this->actingAs($other)
            ->get(route('tasks.attachments.download', [$project, $task, $attachment]))
            ->assertForbidden();

        $this->actingAs($owner);
        $anotherTask = app(CreateTask::class)->handle($owner, $project, 'Second task');
        $this->get(route('tasks.attachments.download', [$project, $anotherTask, $attachment]))
            ->assertNotFound();

        [$otherProject] = $this->taskWithOwner('OTHER');
        $this->get(route('tasks.attachments.download', [$otherProject, $task, $attachment]))
            ->assertNotFound();
    }

    public function test_preview_is_available_only_for_images(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner)->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [
                UploadedFile::fake()->createWithContent('screenshot.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1cAAAAASUVORK5CYII=')),
                UploadedFile::fake()->create('instructions.pdf', 10, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $image = $task->attachments()->where('mime_type', 'image/png')->firstOrFail();
        $pdf = $task->attachments()->where('mime_type', 'application/pdf')->firstOrFail();
        $this->get(route('tasks.attachments.preview', [$project, $task, $image]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get(route('tasks.attachments.preview', [$project, $task, $pdf]))->assertNotFound();
    }

    public function test_uploaded_files_are_validated_by_type_size_and_count(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $route = route('tasks.attachments.store', [$project, $task]);

        $this->actingAs($owner)->post($route, [
            'files' => [UploadedFile::fake()->create('dangerous.svg', 2, 'image/svg+xml')],
        ])->assertSessionHasErrors('files.0');

        $this->post($route, ['files' => [UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf')]])
            ->assertSessionHasErrors('files.0');

        $this->post($route, ['files' => array_fill(0, 6, UploadedFile::fake()->create('many.txt', 1, 'text/plain'))])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, $task->attachments()->count());
    }

    public function test_non_contributor_cannot_upload_or_delete_even_when_the_task_is_visible(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner)->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('readme.txt', 2, 'text/plain')],
        ])->assertSessionHasNoErrors();
        $attachment = $task->attachments()->sole();

        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $this->actingAs($viewer)
            ->get(route('tasks.attachments.download', [$project, $task, $attachment]))
            ->assertOk();
        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('blocked.pdf', 10, 'application/pdf')],
        ])->assertForbidden();
        $this->delete(route('tasks.attachments.destroy', [$project, $task, $attachment]))
            ->assertForbidden();
        $this->assertSame(1, $task->attachments()->count());
    }

    public function test_deletion_removes_private_file_and_archiving_blocks_changes(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $this->actingAs($owner)->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('spec.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $attachment = $task->attachments()->sole();
        $path = $attachment->path;

        $project->forceFill(['archived_at' => now()])->save();
        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('another.pdf', 10, 'application/pdf')],
        ])->assertForbidden();
        $this->delete(route('tasks.attachments.destroy', [$project, $task, $attachment]))->assertForbidden();
        $this->get(route('tasks.attachments.download', [$project, $task, $attachment]))->assertOk();

        $project->forceFill(['archived_at' => null])->save();
        $this->delete(route('tasks.attachments.destroy', [$project, $task, $attachment]))
            ->assertRedirect();
        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }

    /** @return array{\App\Models\Project, User, Task} */
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
        $task = app(CreateTask::class)->handle($owner, $project, 'Attachment task');

        return [$project, $owner, $task];
    }
}
