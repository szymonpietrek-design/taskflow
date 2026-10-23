<?php

namespace Tests\Feature\Integrations\Vcs;

use App\Integrations\Vcs\Ai\PullRequestAiContextBuilder;
use App\Integrations\Vcs\Providers\GitHubInstance;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\VcsConnection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class PullRequestAiContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_default_never_includes_patch_contents_and_does_not_mutate_rows(): void
    {
        [$project, $repository, $pr] = $this->seedRepository();
        $this->file($pr, 'app/Order.php', "@@ -1 +1 @@\n-old\n+new");
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();
        $repository->commits()->create([
            'sha' => self::SHA, 'message' => "Fix UI\nInternal details",
            'committed_at' => now(), 'author_login' => 'coder',
        ]);
        $pr->reviews()->create([
            'external_review_id' => '101', 'state' => 'approved',
            'reviewer_login' => 'reviewer', 'submitted_at' => now(),
        ]);
        $pr->reviews()->create([
            'external_review_id' => '102', 'state' => 'pending', 'reviewer_login' => 'draft',
        ]);

        $before = $pr->files_synced_at->toIso8601String();
        $data = app(PullRequestAiContextBuilder::class)->build($project, 3);

        $this->assertFalse($data['transmitted_to_ai']);
        $this->assertSame('current', $data['files_snapshot']['status']);
        $this->assertSame(1, $data['files_count']);
        $this->assertCount(1, $data['files']);
        $this->assertArrayNotHasKey('untrusted_patch', $data['files'][0]);
        $this->assertSame(0, $data['patches_included']);
        $this->assertCount(1, $data['reviews']);
        $this->assertSame('approved', $data['reviews'][0]['state']);
        $this->assertCount(1, $data['recent_repository_commits_unrelated_to_pr']);
        $this->assertSame($before, $pr->fresh()->files_synced_at->toIso8601String());
        $this->assertDatabaseCount('repository_change_request_files', 1);
    }

    public function test_explicit_patch_opt_in_omits_sensitive_patches_without_leaking_text(): void
    {
        [$project, , $pr] = $this->seedRepository();
        $this->file($pr, 'app/Safe.php', "@@ -1 +1 @@\n-old\n+new");
        $this->file($pr, 'app/Unsafe.php', "@@ -1 +1 @@\n+password = 'do not share'");
        $this->file($pr, '.env', "@@ -1 +1 @@\n+MY_KEY=abc");
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();

        $data = app(PullRequestAiContextBuilder::class)->build($project, 3, includePatches: true);
        $this->assertSame(1, $data['patches_included']);
        $this->assertSame(1, $data['patches_skipped_by_reason']['possible_secret']);
        $this->assertSame(1, $data['patches_skipped_by_reason']['sensitive_path']);
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('do not share', $json);
        $this->assertStringContainsString('@@ -1 +1 @@', $json);
    }

    public function test_old_head_never_returns_old_diff_even_with_explicit_opt_in(): void
    {
        [$project, , $pr] = $this->seedRepository();
        $this->file($pr, 'app/A.php', '@@ OLD SECRET CODE');
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => str_repeat('b', 40)])->save();

        $data = app(PullRequestAiContextBuilder::class)->build($project, 3, includePatches: true);
        $this->assertSame('missing_or_stale', $data['files_snapshot']['status']);
        $this->assertSame([], $data['files']);
        $this->assertSame(0, $data['patches_included']);
    }

    public function test_file_list_is_bounded_and_marks_omitted_entries(): void
    {
        [$project, , $pr] = $this->seedRepository();
        for ($i = 1; $i <= 51; $i++) {
            $this->file($pr, 'src/File'.$i.'.php', "@@ -1 +1 @@\n-old\n+new");
        }
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();

        $context = app(PullRequestAiContextBuilder::class)->build($project, 3);
        $this->assertSame(51, $context['files_count']);
        $this->assertCount(50, $context['files']);
        $this->assertTrue($context['files_omitted']);
        $this->assertSame(0, $context['patches_included']);
    }

    public function test_context_is_bounded_and_never_includes_raw_review_messages_or_pr_body(): void
    {
        [$project, $repository, $pr] = $this->seedRepository();
        $pr->forceFill(['body' => 'Internal discussion that is not approved for external use'])->save();
        for ($i = 1; $i <= 32; $i++) {
            $pr->reviews()->create([
                'external_review_id' => (string) $i,
                'state' => 'commented',
                'reviewer_login' => 'member-'.$i,
                'body' => 'private review content',
                'submitted_at' => now(),
            ]);
        }
        for ($i = 1; $i <= 12; $i++) {
            $repository->commits()->create([
                'sha' => str_pad(dechex($i), 40, '0', STR_PAD_LEFT),
                'message' => 'Maintenance '.$i,
                'committed_at' => now()->addSeconds($i),
            ]);
        }
        $context = app(PullRequestAiContextBuilder::class)->build($project, 3);
        $this->assertCount(30, $context['reviews']);
        $this->assertTrue($context['reviews_omitted']);
        $this->assertCount(10, $context['recent_repository_commits_unrelated_to_pr']);
        $this->assertTrue($context['repository_commits_omitted']);
        $json = json_encode($context, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('private review content', $json);
        $this->assertStringNotContainsString('Internal discussion that is not approved', $json);
    }

    public function test_explicit_pr_identity_is_scoped_to_its_project(): void
    {
        [$firstProject, , $firstPull] = $this->seedRepository();
        [$secondProject, $secondRepository, $secondPull] = $this->seedRepository('11');
        $this->file($firstPull, 'src/First.php', "@@ -1 +1 @@\n-old\n+first");
        $this->file($secondPull, 'src/Second.php', "@@ -1 +1 @@\n-old\n+second");
        $firstPull->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();
        $secondPull->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();

        $builder = app(PullRequestAiContextBuilder::class);
        $context = $builder->buildForPull($secondProject, $secondPull, includePatches: true);

        $this->assertSame($secondRepository->id, $context['repository_id']);
        $this->assertSame('src/Second.php', $context['files'][0]['path']);
        $this->assertStringContainsString('+second', $context['files'][0]['untrusted_patch']);
        $this->assertStringNotContainsString('src/First.php', json_encode($context));

        $this->expectException(ModelNotFoundException::class);
        $builder->buildForPull($firstProject, $secondPull, includePatches: true);
    }

    public function test_does_not_cross_project_boundary(): void
    {
        [$project] = $this->seedRepository();
        $other = Project::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        app(PullRequestAiContextBuilder::class)->build($other, 3, includePatches: true);
    }

    public function test_preview_command_does_not_print_source_code_or_patch(): void
    {
        [$project, , $pr] = $this->seedRepository();
        $this->file($pr, 'src/Test.php', "@@ -1 +1 @@\n-OLD_PRIVATE\n+NEW_PRIVATE");
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();

        $this->artisan('vcs:preview-ai-context', [
            'project' => $project->id, 'number' => 3, '--patches' => true,
        ])->expectsOutput('Snapshot: current')
            ->expectsOutput('File records: 1')
            ->expectsOutput('Candidate patches included in memory: 1')
            ->assertExitCode(0);
    }

    public function test_empty_commit_message_is_safe_to_include_as_a_summary(): void
    {
        [$project, $repository] = $this->seedRepository();
        $repository->commits()->create([
            'sha' => str_repeat('c', 40),
            'message' => '',
            'committed_at' => now(),
        ]);

        $context = app(PullRequestAiContextBuilder::class)->build($project, 3);

        $this->assertSame('', $context['recent_repository_commits_unrelated_to_pr'][0]['message_summary']);
    }

    public function test_preview_identifies_unrelated_commit_limit_without_claiming_pr_files_are_incomplete(): void
    {
        [$project, $repository, $pr] = $this->seedRepository();
        $this->file($pr, 'src/Test.php', "@@ -1 +1 @@\n-before\n+after");
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();
        for ($i = 1; $i <= 11; $i++) {
            $repository->commits()->create([
                'sha' => str_pad(dechex($i), 40, '0', STR_PAD_LEFT),
                'message' => 'Repository commit '.$i,
                'committed_at' => now()->addSeconds($i),
            ]);
        }

        $this->assertSame(0, Artisan::call('vcs:preview-ai-context', [
            'project' => $project->id,
            'number' => 3,
        ]));
        $output = Artisan::output();
        $this->assertStringContainsString('Snapshot: current', $output);
        $this->assertStringContainsString('File entries in context: 1', $output);
        $this->assertStringContainsString('Recent repository commits capped at 10 (not PR-specific)', $output);
        $this->assertStringNotContainsString('The context is incomplete', $output);
        $this->assertStringNotContainsString('@@ -1 +1 @@', $output);
    }

    public function test_preview_warns_separately_when_pr_files_and_reviews_exceed_limits(): void
    {
        [$project, , $pr] = $this->seedRepository();
        for ($i = 1; $i <= 51; $i++) {
            $this->file($pr, 'src/File'.$i.'.php', "@@ -1 +1 @@\n-old\n+new");
        }
        for ($i = 1; $i <= 31; $i++) {
            $pr->reviews()->create([
                'external_review_id' => (string) $i,
                'state' => 'commented',
                'submitted_at' => now(),
            ]);
        }
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();

        $this->assertSame(0, Artisan::call('vcs:preview-ai-context', [
            'project' => $project->id,
            'number' => 3,
        ]));
        $output = Artisan::output();
        $this->assertStringContainsString('PR file list capped at 50 entries', $output);
        $this->assertStringContainsString('Submitted review list capped at 30 entries', $output);
        $this->assertStringNotContainsString('Some entries exceeded the preview limits', $output);
    }

    public function test_preview_stale_snapshot_skips_all_file_contents_even_with_opt_in(): void
    {
        [$project, , $pr] = $this->seedRepository();
        $this->file($pr, 'src/Test.php', "@@ -1 +1 @@\n+NOT_FOR_OUTPUT");
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => str_repeat('b', 40)])->save();

        $this->assertSame(0, Artisan::call('vcs:preview-ai-context', [
            'project' => $project->id,
            'number' => 3,
            '--patches' => true,
        ]));
        $output = Artisan::output();
        $this->assertStringContainsString('Snapshot: missing_or_stale', $output);
        $this->assertStringContainsString('no file entries or patches were included', $output);
        $this->assertStringContainsString('Candidate patches included in memory: 0', $output);
        $this->assertStringNotContainsString('NOT_FOR_OUTPUT', $output);
    }

    public function test_preview_does_not_print_rejected_patch_text_or_secret(): void
    {
        [$project, , $pr] = $this->seedRepository();
        $this->file($pr, 'src/Config.php', "@@ -1 +1 @@\n+secret = 'DO_NOT_EXPOSE_THIS'");
        $pr->forceFill(['files_synced_at' => now(), 'files_head_sha' => self::SHA])->save();

        $this->assertSame(0, Artisan::call('vcs:preview-ai-context', [
            'project' => $project->id,
            'number' => 3,
            '--patches' => true,
        ]));
        $output = Artisan::output();
        $this->assertStringContainsString('possible_secret', $output);
        $this->assertStringContainsString('Some optional patch candidates were screened out', $output);
        $this->assertStringNotContainsString('DO_NOT_EXPOSE_THIS', $output);
    }

    /** @return array{Project,RepositoryConnection,RepositoryChangeRequest} */
    private function seedRepository(string $externalConnectionId = '10'): array
    {
        $project = Project::factory()->create();
        $connection = VcsConnection::query()->create([
            'workspace_id' => $project->workspace_id,
            'vcs_instance_id' => GitHubInstance::get()->id,
            'external_connection_id' => $externalConnectionId, 'account_login' => 'example', 'connected_at' => now(),
        ]);
        $repository = RepositoryConnection::query()->create([
            'project_id' => $project->id, 'vcs_connection_id' => $connection->id,
            'external_repository_id' => '20', 'full_name' => 'example/repo',
            'default_branch' => 'main', 'start_sha' => self::SHA,
            'last_synced_sha' => self::SHA, 'connected_at' => now(),
        ]);
        $pr = $repository->changeRequests()->create([
            'external_change_request_id' => '123', 'number' => 3,
            'title' => 'Improve checkout', 'state' => 'open',
            'web_url' => 'https://github.com/example/repo/pull/3',
            'head_sha' => self::SHA, 'remote_updated_at' => now(),
        ]);

        return [$project, $repository, $pr];
    }

    private function file(RepositoryChangeRequest $pr, string $path, string $patch): void
    {
        $pr->files()->create([
            'path' => $path, 'path_hash' => hash('sha256', $path),
            'status' => 'modified', 'additions' => 2, 'deletions' => 1, 'changes' => 3,
            'patch' => $patch, 'patch_truncated' => false,
        ]);
    }
}
