<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\ReportPromptArchive;
use App\Services\Ai\ReportPromptBuilder;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ReportPromptDebugTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    public function test_project_summary_builder_keeps_exact_json_and_requested_language(): void
    {
        $context = '{"report_format":"project_summary","tasks":[{"title":"A & B"}]}';
        $messages = app(ReportPromptBuilder::class)->build($context, 'pl');
        $this->assertSame('project_summary', $messages['format']);
        $this->assertStringContainsString('language denoted by BCP 47 tag: pl.', $messages['system']);
        $this->assertStringContainsString('Write a clear project summary', $messages['system']);
        $this->assertStringContainsString('counts_by_type', $messages['system']);
        $this->assertSame("Prepare the project report using only this JSON data:\n".$context, $messages['user']);
    }

    public function test_summary_counts_committed_code_as_work_without_requiring_a_completed_task(): void
    {
        $messages = app(ReportPromptBuilder::class)->build(
            '{"report_format":"project_summary","selected_sources":["tasks","vcs"],"tasks":[{"completed_in_period":false}],"vcs":{"commits":[{"subject":"feat: add Git integration"}]}}',
            'pl',
        );

        $prompt = $messages['system'];
        $this->assertStringContainsString('Treat commits as evidence of work performed in the codebase', $prompt);
        $this->assertStringContainsString('even when the corresponding task', $prompt);
        $this->assertStringContainsString('group related changes into 3-6 results', $prompt);
        $this->assertStringContainsString('Project progress: distinguish completed programming work from formally', $prompt);
        $this->assertStringContainsString('even if a TaskFlow task', $prompt);
        $this->assertStringContainsString('completed task still requires completed_in_period=true', $prompt);
        $this->assertStringContainsString('Favor readable functionality and benefit descriptions', $prompt);
        $this->assertStringContainsString('Lack of deployment data is not automatically a', $prompt);
    }

    public function test_summary_distinguishes_pr_integration_from_production_deployment(): void
    {
        $system = app(ReportPromptBuilder::class)->build('{"report_format":"project_summary"}', 'en')['system'];

        $this->assertStringContainsString('An open PR is code submitted for review or merge', $system);
        $this->assertStringContainsString('A merged PR with a merged_at', $system);
        $this->assertStringContainsString('but does not prove a production deployment', $system);
        $this->assertStringContainsString('A merged_in_period=true value', $system);
        $this->assertStringContainsString('never assume a commit is on main/master', $system);
        $this->assertStringContainsString('Do not double-count a change', $system);
        $this->assertStringContainsString('Do not add a standard disclaimer', $system);
    }

    public function test_summary_uses_bounded_commit_file_metadata_but_not_source_patches(): void
    {
        $system = app(ReportPromptBuilder::class)->build('{"report_format":"project_summary"}', 'pl')['system'];
        $this->assertStringContainsString('changed-file metadata', $system);
        $this->assertStringContainsString('File names and line counts are NOT code contents', $system);
        $this->assertStringContainsString('files_included less', $system);
        $this->assertStringContainsString('No raw patches or source', $system);
    }

    public function test_technical_and_project_reports_use_distinct_prompts_with_same_source_json(): void
    {
        $vcs = ['commits' => [['sha' => 'abc123', 'subject' => 'Add OAuth login', 'files' => [['path' => 'app/AuthController.php']]]]];
        $base = ['selected_sources' => ['tasks', 'vcs'], 'vcs' => $vcs];
        $technicalJson = json_encode(['report_format' => 'technical_report'] + $base, JSON_THROW_ON_ERROR);
        $projectJson = json_encode(['report_format' => 'project_summary'] + $base, JSON_THROW_ON_ERROR);
        $technical = app(ReportPromptBuilder::class)->build($technicalJson, 'pl');
        $project = app(ReportPromptBuilder::class)->build($projectJson, 'pl');

        $this->assertSame('technical_report', $technical['format']);
        $this->assertStringContainsString('TECHNICAL REPORT for developers', $technical['system']);
        $this->assertStringContainsString('controllers, models, migrations', $technical['system']);
        $this->assertStringContainsString('A test file', $technical['system']);
        $this->assertStringNotContainsString('PROJECT REPORT for a project manager', $technical['system']);
        $this->assertStringContainsString('PROJECT REPORT for a project manager', $project['system']);
        $this->assertStringContainsString('benefit descriptions over class names', $project['system']);
        $this->assertStringNotContainsString('TECHNICAL REPORT for developers', $project['system']);
        $this->assertStringContainsString($technicalJson, $technical['user']);
        $this->assertStringContainsString($projectJson, $project['user']);
        $this->assertStringContainsString('No raw patches or source', $technical['system']);
        $this->assertStringContainsString('single-backtick inline Markdown code', $technical['system']);
        $this->assertStringContainsString('what changed', $technical['system']);
        $this->assertStringContainsString('NOT a flat inventory of files', $technical['system']);
        $this->assertStringContainsString('250-400 words', $project['system']);
        $this->assertStringContainsString('value to users or the team', $project['system']);
        $this->assertStringContainsString('Do not enumerate controller names', $project['system']);
        $this->assertStringNotContainsString('Aim for roughly 250-400 words', $technical['system']);
        $this->assertStringNotContainsString('Target roughly 350-550 words', $project['system']);
    }

    public function test_report_prompts_use_authoritative_vcs_counts_and_hide_internal_evidence_keys(): void
    {
        foreach (['project_summary', 'technical_report'] as $format) {
            $system = app(ReportPromptBuilder::class)->build('{"report_format":"'.$format.'"}', 'pl')['system'];
            $this->assertStringContainsString('vcs.commit_count and vcs.pull_request_count', $system);
            $this->assertStringContainsString('not numbers of changed files', $system);
            $this->assertStringContainsString('Translate their MEANING into natural language', $system);
            $this->assertStringContainsString('NEVER repeat raw', $system);
        }
    }

    public function test_work_report_builder_uses_json_only_instruction(): void
    {
        $messages = app(ReportPromptBuilder::class)->build('{"report_format":"work_report"}', 'en');
        $this->assertSame('work_report', $messages['format']);
        $this->assertStringContainsString('Return one valid JSON object', $messages['system']);
    }

    public function test_snapshot_contains_both_unredacted_messages_and_uses_private_permissions(): void
    {
        $this->directory = sys_get_temp_dir().'/taskflow-prompt-'.bin2hex(random_bytes(6));
        $path = (new ReportPromptArchive($this->directory))->save(
            22, 'pl', 'deepseek-flash', 2000,
            'system test', 'user test with confidential data',
        );
        $saved = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(22, $saved['report_id']);
        $this->assertSame('system test', $saved['messages'][0]['content']);
        $this->assertSame('user test with confidential data', $saved['messages'][1]['content']);
        $this->assertSame('deepseek-flash', $saved['model']);
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame("*\n!.gitignore\n", file_get_contents($this->directory.'/.gitignore'));
    }

    public function test_console_refuses_to_export_prompt_outside_local_environment(): void
    {
        // PHPUnit uses APP_ENV=testing; the CLI is intentionally for local only.
        $this->artisan('ai:inspect-report-prompt', ['report' => 22])
            ->expectsOutput('Prompt inspection is available only with APP_ENV=local.')
            ->assertExitCode(1);
    }
}
