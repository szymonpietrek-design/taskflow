<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\ReportPromptArchive;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

final class ReportPromptRetentionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/taskflow-prompt-retention-'.bin2hex(random_bytes(6));
        config()->set('services.ai_chat.prompt_archive_retention_days', 7);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_saving_a_new_snapshot_prunes_only_expired_generated_snapshots(): void
    {
        $archive = new ReportPromptArchive($this->directory);
        $old = $archive->save(22, 'pl', 'deepseek-chat', 2000, 'system', 'secret');
        $kept = $archive->save(23, 'pl', 'deepseek-chat', 2000, 'system', 'recent');
        touch($old, time() - 9 * 86400);

        $unrelated = $this->directory.'/unrelated.json';
        file_put_contents($unrelated, 'keep');
        touch($unrelated, time() - 9 * 86400);
        $unmatched = $this->directory.'/report-22-not-a-generated-file.json';
        file_put_contents($unmatched, 'keep');
        touch($unmatched, time() - 9 * 86400);

        $archive->save(24, 'en', 'deepseek-chat', 2000, 'system', 'fresh');

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($kept);
        $this->assertFileExists($unrelated);
        $this->assertFileExists($unmatched);
        $this->assertFileExists($this->directory.'/.gitignore');
    }

    public function test_retention_days_are_clamped_and_manual_prune_returns_removed_count(): void
    {
        config()->set('services.ai_chat.prompt_archive_retention_days', -2); // min 1 day
        $archive = new ReportPromptArchive($this->directory);
        $path = $archive->save(33, 'pl', 'deepseek-chat', 2000, 'system', 'message');
        touch($path, time() - 2 * 86400);

        $this->assertSame(1, $archive->prune());
        $this->assertSame(0, $archive->prune());
    }

    public function test_save_and_prune_refuse_production_even_with_an_explicit_directory(): void
    {
        $archive = new ReportPromptArchive($this->directory);
        $before = $this->app['env'];
        try {
            $this->app['env'] = 'production';
            $this->expectException(RuntimeException::class);
            $archive->save(1, 'en', 'deepseek-chat', 2000, 'sys', 'secret');
        } finally {
            $this->app['env'] = $before;
        }
    }

    public function test_cleanup_command_is_not_available_in_testing_or_production(): void
    {
        $this->artisan('ai:prune-report-prompts')
            ->expectsOutput('Prompt archive cleanup is available only with APP_ENV=local.')
            ->assertExitCode(1);
    }
}
