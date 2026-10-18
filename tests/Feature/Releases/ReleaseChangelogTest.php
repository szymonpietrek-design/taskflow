<?php

namespace Tests\Feature\Releases;

use App\Actions\Releases\PublishReleaseChangelog;
use App\Actions\Releases\SaveReleaseChangelog;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\ReleaseStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseChangelog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReleaseChangelogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_manager_can_save_draft_and_preview_without_publishing(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), [
            'draft_body' => "  ## Added\n- Export reports  ",
        ])->assertSessionHasNoErrors()->assertRedirect(route('releases.show', [$project, $release]));

        $changelog = ReleaseChangelog::query()->sole();
        $this->assertSame($release->id, $changelog->release_id);
        $this->assertSame("## Added\n- Export reports", $changelog->draft_body);
        $this->assertSame($owner->id, $changelog->updated_by);
        $this->assertNull($changelog->published_body);
        $this->assertNull($changelog->published_at);

        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('releases/Show')
            ->where('can_edit_changelog', true)
            ->where('changelog.draft_body', "## Added\n- Export reports")
            ->where('changelog.has_unpublished_changes', true)
            ->where('changelog.published_html', null)
            ->where('changelog.draft_html', fn (string $html) => str_contains($html, '<h2>Added</h2>')));
    }

    public function test_release_must_be_released_before_publishing_changelog(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => '## Notes'])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post($this->publishUrl($project, $release))
            ->assertSessionHasErrors(['changelog']);
        $this->assertNull(ReleaseChangelog::query()->sole()->published_at);
    }

    public function test_publishing_creates_a_snapshot_and_edits_do_not_modify_it(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $release->update(['status' => ReleaseStatus::RELEASED, 'released_at' => now()]);
        $this->freezeTime();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => '## Version 1'])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post($this->publishUrl($project, $release))->assertSessionHasNoErrors();
        $changelog = ReleaseChangelog::query()->sole();
        $this->assertSame('## Version 1', $changelog->published_body);
        $this->assertSame($owner->id, $changelog->published_by);
        $this->assertSame(now()->toDateTimeString(), $changelog->published_at?->toDateTimeString());

        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => '## Version 2'])
            ->assertSessionHasNoErrors();
        $this->assertSame('## Version 1', $changelog->fresh()->published_body);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('changelog.has_unpublished_changes', true)
                ->where('changelog.draft_body', '## Version 2')
                ->where('changelog.published_html', fn (string $html) => str_contains($html, 'Version 1')));

        $this->actingAs($owner)->post($this->publishUrl($project, $release))->assertSessionHasNoErrors();
        $this->assertSame('## Version 2', $changelog->fresh()->published_body);
    }

    public function test_empty_draft_cannot_be_published_and_content_is_validated(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $release->update(['status' => ReleaseStatus::RELEASED]);
        $this->actingAs($owner)->post($this->publishUrl($project, $release))
            ->assertSessionHasErrors(['changelog']);
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => []])
            ->assertSessionHasErrors(['draft_body']);
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => str_repeat('x', 50001)])
            ->assertSessionHasErrors(['draft_body']);
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => '    '])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post($this->publishUrl($project, $release))
            ->assertSessionHasErrors(['changelog']);
    }

    public function test_clearing_draft_does_not_unpublish_previous_changelog(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $release->update(['status' => ReleaseStatus::RELEASED]);
        app(SaveReleaseChangelog::class)->handle($owner, $release, 'Public');
        app(PublishReleaseChangelog::class)->handle($owner, $release);

        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => ''])
            ->assertSessionHasNoErrors();
        $changelog = ReleaseChangelog::query()->sole();
        $this->assertSame('', $changelog->draft_body);
        $this->assertSame('Public', $changelog->published_body);
        $this->actingAs($owner)->post($this->publishUrl($project, $release))
            ->assertSessionHasErrors(['changelog']);
    }

    public function test_noop_draft_save_does_not_update_editor_or_timestamp(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => 'Same'])
            ->assertSessionHasNoErrors();
        $before = ReleaseChangelog::query()->sole();
        $updated = $before->updated_at->toDateTimeString();
        $this->travel(2)->minutes();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => 'Same'])
            ->assertSessionHasNoErrors();
        $this->assertSame($updated, $before->fresh()->updated_at->toDateTimeString());
    }

    public function test_reader_can_only_see_published_content_and_cannot_edit(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $release->update(['status' => ReleaseStatus::RELEASED]);
        app(SaveReleaseChangelog::class)->handle($owner, $release, 'Visible notes');
        app(PublishReleaseChangelog::class)->handle($owner, $release);
        app(SaveReleaseChangelog::class)->handle($owner, $release, 'Private changes');

        $reader = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $reader->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $this->actingAs($reader)->get(route('releases.show', [$project, $release]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('releases/Show')
            ->where('can_edit_changelog', false)
            ->where('changelog.draft_body', '')
            ->where('changelog.draft_html', null)
            ->where('changelog.published_html', fn (string $html) => str_contains($html, 'Visible notes') && ! str_contains($html, 'Private changes')));
        $this->actingAs($reader)->put($this->draftUrl($project, $release), ['draft_body' => 'Unauthorized'])
            ->assertForbidden();
        $this->actingAs($reader)->post($this->publishUrl($project, $release))
            ->assertForbidden();
    }

    public function test_markdown_preview_escapes_raw_html_and_unsafe_links(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), [
            'draft_body' => "## Safe\n<script>alert('bad')</script>\n[bad](javascript:alert(1))",
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('changelog.draft_html', function (string $html): bool {
                    return str_contains($html, '<h2>Safe</h2>')
                        && ! str_contains($html, '<script')
                        && ! str_contains($html, 'href="javascript:');
                }));
    }

    public function test_canceled_and_archived_releases_are_readonly(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $release->update(['status' => ReleaseStatus::CANCELED]);
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => 'Bad'])
            ->assertSessionHasErrors(['draft_body']);
        $project->update(['archived_at' => now()]);
        $this->actingAs($owner)->get(route('releases.show', [$project, $release]))
            ->assertInertia(fn (Assert $page) => $page->where('can_edit_changelog', false));
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => 'Bad'])
            ->assertForbidden();
        $this->assertDatabaseCount('release_changelogs', 0);
    }

    public function test_other_project_and_standard_project_are_blocked(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $owner->id,
            'type' => ProjectType::APPLICATION,
        ]);
        $this->actingAs($owner)->put($this->draftUrl($other, $release), ['draft_body' => 'Bad'])
            ->assertNotFound();
        $this->actingAs($owner)->post($this->publishUrl($other, $release))
            ->assertNotFound();
        $project->update(['type' => ProjectType::STANDARD]);
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => 'Bad'])
            ->assertNotFound();
    }

    public function test_private_project_is_inaccessible_to_nonmember(): void
    {
        [, $project, $release] = $this->fixtures();
        $project->update(['visibility' => ProjectVisibility::PRIVATE]);
        $outsider = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $outsider->id,
            'role' => WorkspaceRole::MEMBER,
        ]);
        $this->actingAs($outsider)->get(route('releases.show', [$project, $release]))->assertForbidden();
        $this->actingAs($outsider)->put($this->draftUrl($project, $release), ['draft_body' => 'Bad'])
            ->assertForbidden();
    }

    public function test_one_changelog_per_release(): void
    {
        [$owner, $project, $release] = $this->fixtures();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => 'First'])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->put($this->draftUrl($project, $release), ['draft_body' => 'Second'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('release_changelogs', 1);
        $this->assertSame('Second', ReleaseChangelog::query()->sole()->draft_body);
    }

    /** @return array{User, Project, Release} */
    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['created_by' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER,
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

    private function draftUrl(Project $project, Release $release): string
    {
        return route('releases.changelog.update', [$project, $release]);
    }

    private function publishUrl(Project $project, Release $release): string
    {
        return route('releases.changelog.publish', [$project, $release]);
    }
}
