<?php

namespace Tests\Feature\Acceptance;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\WorkspaceRole;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAccessAndArchiveSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_project_member_loses_private_task_and_message_file_access_after_removal(): void
    {
        [$owner, $workspace, $project, $task] = $this->fixture();
        $member = User::factory()->create();
        $grant = ProjectMember::factory()->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER,
        ]);
        $legacyMember = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $legacyMember->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $outsider = User::factory()->create();

        $this->actingAs($owner)->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('spec.pdf', 2, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Review the test result',
            'files' => [UploadedFile::fake()->create('results.txt', 2, 'text/plain')],
        ])->assertSessionHasNoErrors();

        $taskFile = $task->attachments()->where('original_name', 'spec.pdf')->firstOrFail();
        $message = $task->messages()->sole();
        $messageFile = $message->attachments()->sole();
        $taskUrl = route('tasks.show', [$project, $task]);
        $taskFileUrl = route('tasks.attachments.download', [$project, $task, $taskFile]);
        $messageFileUrl = route('tasks.attachments.download', [$project, $task, $messageFile]);

        // A project grant enables access to both kinds of private attachment.
        $this->actingAs($member)->get($taskUrl)->assertOk();
        $this->get($taskFileUrl)->assertOk();
        $this->get($messageFileUrl)->assertOk();

        // Workspace membership without a project grant is not enough.
        foreach ([$legacyMember, $outsider] as $blockedUser) {
            $this->actingAs($blockedUser)->get($taskUrl)->assertForbidden();
            $this->get($taskFileUrl)->assertForbidden();
            $this->get($messageFileUrl)->assertForbidden();
        }

        $this->actingAs($owner)->delete(route('projects.members.destroy', [$project, $grant]))
            ->assertSessionHasNoErrors();
        $this->actingAs($member)->get($taskUrl)->assertForbidden();
        $this->get($taskFileUrl)->assertForbidden();
        $this->get($messageFileUrl)->assertForbidden();

        // Revocation must not erase the project's own historical files or messages.
        $this->assertDatabaseHas('task_messages', ['id' => $message->id, 'task_id' => $task->id]);
        Storage::disk('local')->assertExists($taskFile->path);
        Storage::disk('local')->assertExists($messageFile->path);
        $this->actingAs($owner)->get($messageFileUrl)->assertOk();
    }

    public function test_archiving_preserves_manager_read_access_and_blocks_writes_across_modules(): void
    {
        [$owner, $workspace, $project, $task] = $this->fixture();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $project->id, 'user_id' => $manager->id, 'role' => ProjectRole::MANAGER,
        ]);
        ProjectMember::factory()->create([
            'project_id' => $project->id, 'user_id' => $member->id, 'role' => ProjectRole::MEMBER,
        ]);

        $this->actingAs($owner)->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'Archived history should remain readable',
            'files' => [UploadedFile::fake()->create('history.txt', 2, 'text/plain')],
        ])->assertSessionHasNoErrors();
        $message = $task->messages()->sole();
        $attachment = $message->attachments()->sole();

        $today = now()->toDateString();
        $this->post(route('reports.store', $project), [
            'title' => 'Archive acceptance report',
            'format' => 'project_summary',
            'period_start' => $today,
            'period_end' => $today,
            'sources' => ['tasks'],
        ])->assertSessionHasNoErrors();
        $report = $project->reports()->sole();
        $this->post(route('releases.store', $project), [
            'version' => '1.0.0', 'name' => 'First release',
        ])->assertSessionHasNoErrors();
        $release = $project->releases()->sole();

        $this->patch(route('workspaces.projects.archive', [$workspace, $project]))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($project->fresh()->archived_at);

        // Project history remains available to the owner and project manager.
        foreach ([$owner, $manager] as $reader) {
            $this->actingAs($reader)->get(route('projects.show', $project))->assertOk();
            $this->get(route('tasks.show', [$project, $task]))->assertOk();
            $this->get(route('tasks.attachments.download', [$project, $task, $attachment]))->assertOk();
            $this->get(route('reports.show', [$project, $report]))->assertOk();
            $this->get(route('releases.show', [$project, $release]))->assertOk();
        }

        // Archived projects cannot be altered, even by their owners.
        $this->actingAs($owner)->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'This must not be saved',
        ])->assertForbidden();
        $this->post(route('tasks.attachments.store', [$project, $task]), [
            'files' => [UploadedFile::fake()->create('blocked.txt', 2, 'text/plain')],
        ])->assertForbidden();
        $this->post(route('releases.store', $project), [
            'version' => '1.0.1', 'name' => 'Blocked release',
        ])->assertForbidden();
        $this->post(route('reports.store', $project), [
            'title' => 'Blocked report', 'format' => 'project_summary',
            'period_start' => $today, 'period_end' => $today, 'sources' => ['tasks'],
        ])->assertForbidden();
        $this->assertSame(1, $task->messages()->count());
        $this->assertSame(1, $task->attachments()->count());
        $this->assertSame(1, $project->releases()->count());
        $this->assertSame(1, $project->reports()->count());

        // Regular members cannot bypass the archive by using direct URLs.
        $this->actingAs($member)->get(route('tasks.show', [$project, $task]))->assertForbidden();
        $this->get(route('tasks.attachments.download', [$project, $task, $attachment]))->assertForbidden();
        $this->get(route('reports.show', [$project, $report]))->assertForbidden();

        $this->actingAs($owner)->patch(route('workspaces.projects.restore', [$workspace, $project]))
            ->assertSessionHasNoErrors();
        $this->assertNull($project->fresh()->archived_at);
        $this->actingAs($member)->get(route('tasks.show', [$project, $task]))->assertOk();
        $this->actingAs($owner)->post(route('tasks.messages.store', [$project, $task]), [
            'body' => 'The project is editable again',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $task->messages()->count());
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Acceptance workspace');
        $project = app(CreateProject::class)->handle(
            $owner, $workspace, 'Acceptance application', 'E2E', ProjectType::APPLICATION,
        );
        $task = app(CreateTask::class)->handle($owner, $project, 'Private acceptance task');

        return [$owner, $workspace, $project, $task];
    }
}
