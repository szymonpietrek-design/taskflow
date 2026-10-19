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

class TaskMessageMarkdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_message_renders_linked_images_and_download_links_without_modifying_source_markdown(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $image = $this->upload($project, $task, 'screen.png', 'image/png');
        $pdf = $this->upload($project, $task, 'details.pdf', 'application/pdf');
        $body = "### Reproduction\n\n![Screenshot](attachment:{$image->id})\n\n[Open PDF](attachment:{$pdf->id})";

        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => $body,
            'attachment_ids' => [$image->id, $pdf->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame($body, $task->messages()->sole()->body);
        $this->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('messages.0.body', $body)
                ->where('messages.0.body_html', fn (string $html): bool =>
                    str_contains($html, '<h3>Reproduction</h3>')
                    && str_contains($html, route('tasks.attachments.preview', [$project, $task, $image], false))
                    && str_contains($html, route('tasks.attachments.download', [$project, $task, $pdf], false))
                    && str_contains($html, '<img ')
                    && str_contains($html, 'Open PDF')));
    }

    public function test_unlinked_ids_and_non_image_files_never_become_embedded_private_urls(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $image = $this->upload($project, $task, 'secret.png', 'image/png');
        $pdf = $this->upload($project, $task, 'document.pdf', 'application/pdf');
        $body = "![Unlinked](attachment:{$image->id})\n\n![Not an image](attachment:{$pdf->id})\n\n<script>alert(1)</script>\n\n[Bad](javascript:alert(1))";

        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => $body, 'attachment_ids' => [$pdf->id],
        ])->assertSessionHasNoErrors();
        $this->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.body_html', fn (string $html): bool =>
                    ! str_contains($html, '/attachments/'.$image->id.'/')
                    && ! str_contains($html, '/attachments/'.$pdf->id.'/preview')
                    && ! str_contains($html, '<img ')
                    && ! str_contains($html, '<script')
                    && ! str_contains($html, 'href="javascript:')
                    && str_contains($html, __('Attachment unavailable.'))));
    }

    public function test_unavailable_image_does_not_prevent_resolving_the_next_valid_image(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $unlinked = $this->upload($project, $task, 'not-linked.png', 'image/png');
        $linked = $this->upload($project, $task, 'linked.png', 'image/png');
        $body = "![Unlinked](attachment:{$unlinked->id})\n\n![Linked](attachment:{$linked->id})";

        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => $body, 'attachment_ids' => [$linked->id],
        ])->assertSessionHasNoErrors();

        $this->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.body_html', fn (string $html): bool =>
                    str_contains($html, __('Attachment unavailable.'))
                    && str_contains($html, route('tasks.attachments.preview', [$project, $task, $linked], false))
                    && ! str_contains($html, route('tasks.attachments.preview', [$project, $task, $unlinked], false))
                    && substr_count($html, '<img ') === 1));
    }

    public function test_markdown_reference_in_code_block_is_not_changed_into_an_image(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $image = $this->upload($project, $task, 'screen.png', 'image/png');
        $body = "```markdown\n![Code](attachment:{$image->id})\n```";

        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => $body, 'attachment_ids' => [$image->id],
        ])->assertSessionHasNoErrors();
        $this->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.body_html', fn (string $html): bool =>
                    str_contains($html, '<code')
                    && str_contains($html, 'attachment:'.$image->id)
                    && ! str_contains($html, '<img ')));
    }

    public function test_composer_preview_allows_only_selected_files_from_current_task(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $image = $this->upload($project, $task, 'image.png', 'image/png');
        $other = app(CreateTask::class)->handle($owner, $project, 'Other task');
        $otherImage = $this->upload($project, $other, 'private.png', 'image/png');
        $url = route('tasks.messages.preview', [$project, $task]);

        $this->postJson($url, [
            'body' => "![Image](attachment:{$image->id})", 'attachment_ids' => [$image->id],
        ])->assertOk()->assertJsonPath('html', app(\App\Support\Tasks\TaskMessageMarkdown::class)
            ->render("![Image](attachment:{$image->id})", $project->id, $task->id, [$image]));

        $this->postJson($url, [
            'body' => "![Unselected](attachment:{$image->id})", 'attachment_ids' => [],
        ])->assertOk()->assertJson(fn ($json) => $json
            ->where('html', fn (string $html): bool => ! str_contains($html, '/attachments/'.$image->id.'/')));

        $this->postJson($url, [
            'body' => "![Wrong task](attachment:{$otherImage->id})", 'attachment_ids' => [$otherImage->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment_ids.0');
        $this->assertDatabaseCount('task_messages', 0);
    }

    public function test_edit_preview_is_private_and_uses_only_attachments_linked_to_the_message(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $image = $this->upload($project, $task, 'image.png', 'image/png');
        $unused = $this->upload($project, $task, 'unused.png', 'image/png');
        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Old message', 'attachment_ids' => [$image->id],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();
        $url = route('tasks.messages.edit-preview', [$project, $task, $message]);

        $this->postJson($url, [
            'body' => "![Used](attachment:{$image->id})\n![Unused](attachment:{$unused->id})",
        ])->assertOk()->assertJson(fn ($json) => $json
            ->where('html', fn (string $html): bool =>
                str_contains($html, '/attachments/'.$image->id.'/preview')
                && ! str_contains($html, '/attachments/'.$unused->id.'/preview')));
        $this->assertSame('Old message', $message->fresh()->body);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson($url, ['body' => 'No'])->assertForbidden();
        $this->actingAs($owner);
        $project->forceFill(['archived_at' => now()])->save();
        $this->postJson($url, ['body' => 'Archived'])->assertForbidden();
    }

    public function test_inline_image_must_be_removed_from_message_before_attachment_can_be_unlinked(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $image = $this->upload($project, $task, 'image.png', 'image/png');
        $body = "![Image](attachment:{$image->id})";
        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => $body, 'attachment_ids' => [$image->id],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();
        $unlinkUrl = route('tasks.messages.attachments.destroy', [$project, $task, $message, $image]);

        // Detaching a referenced image must not break an existing message.
        $this->delete($unlinkUrl)->assertSessionHasErrors('attachment');
        $this->assertSame($body, $message->fresh()->body);
        $this->assertSame(1, $message->attachments()->count());

        // Once the Markdown reference is removed, the old direct unlink route works.
        $this->patch(route('tasks.messages.update', [$project, $task, $message]), [
            'body' => 'Image removed from message',
        ])->assertSessionHasNoErrors();
        $this->delete($unlinkUrl)->assertSessionHasNoErrors();
        $this->assertSame(0, $message->attachments()->count());
        $this->get(route('tasks.show', [$project, $task]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.body_html', fn (string $html): bool =>
                    ! str_contains($html, '/attachments/'.$image->id.'/')
                    && ! str_contains($html, __('Attachment unavailable.'))));
        Storage::disk('local')->assertExists($image->path);
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
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Message attachments', 'ATT');
        $task = app(CreateTask::class)->handle($owner, $project, 'Task details');
        $this->actingAs($owner);

        return [$project, $owner, $task];
    }

    private function upload(\App\Models\Project $project, Task $task, string $name, string $mime): \App\Models\TaskAttachment
    {
        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create($name, 5, $mime)],
        ])->assertSessionHasNoErrors();

        return $task->attachments()->latest('id')->firstOrFail();
    }
}
