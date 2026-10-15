<?php

namespace Tests\Feature\Tasks;

use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Tasks\TaskMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskMarkdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_task_details_render_markdown_without_changing_stored_description(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $description = "### Cel\nPrzetestować scenariusze awaryjne.\n\n### Kryteria akceptacji\n- Pierwszy test\n- Drugi test";
        $task->forceFill(['description' => $description])->save();

        $this->actingAs($owner)
            ->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('task.description', $description)
                ->where('task.description_html', fn (string $html): bool =>
                    str_contains($html, '<h3>Cel</h3>')
                    && str_contains($html, '<h3>Kryteria akceptacji</h3>')
                    && str_contains($html, '<li>Pierwszy test</li>')));

        $this->assertSame($description, $task->fresh()->description);
    }

    public function test_raw_html_and_unsafe_links_are_not_rendered_as_active_markup(): void
    {
        $description = "**OK**\n\n<script>alert('xss')</script>\n\n<a href=\"javascript:alert(1)\">unsafe</a>\n\n[bad](javascript:alert(1))";
        $html = app(TaskMarkdown::class)->render($description);

        $this->assertStringContainsString('<strong>OK</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<a href="javascript:', $html);
        $this->assertStringNotContainsString('<a href="data:', $html);
    }

    public function test_authenticated_editor_can_preview_without_saving(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();

        $this->actingAs($owner)
            ->postJson(route('tasks.description.preview', [$project, $task]), [
                'description' => "### Przykład\n\n- Punkt pierwszy",
            ])
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('html', app(TaskMarkdown::class)->render("### Przykład\n\n- Punkt pierwszy")));

        $this->assertNull($task->fresh()->description);
    }

    public function test_preview_respects_edit_permissions_and_project_scope(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $viewer = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::MEMBER,
        ]);

        $url = route('tasks.description.preview', [$project, $task]);
        $this->actingAs($viewer)->postJson($url, ['description' => 'Preview'])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson($url, ['description' => 'Preview'])->assertForbidden();

        [$otherProject] = $this->taskWithOwner('OTHER');
        $this->actingAs($owner)
            ->postJson(route('tasks.description.preview', [$otherProject, $task]), ['description' => 'Preview'])
            ->assertNotFound();
    }

    public function test_preview_rejects_overlong_input_and_archived_project(): void
    {
        [$project, $owner, $task] = $this->taskWithOwner();
        $url = route('tasks.description.preview', [$project, $task]);

        $this->actingAs($owner)
            ->postJson($url, ['description' => str_repeat('x', 10001)])
            ->assertUnprocessable()->assertJsonValidationErrors('description');

        $project->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner)->postJson($url, ['description' => 'Preview'])->assertForbidden();
    }

    /** @return array{Project,User,Task} */
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
        $task = app(CreateTask::class)->handle($owner, $project, 'Markdown task');

        return [$project, $owner, $task];
    }
}
