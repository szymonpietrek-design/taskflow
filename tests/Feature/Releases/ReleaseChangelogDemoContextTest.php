<?php

namespace Tests\Feature\Releases;

use App\Models\Project;
use App\Models\Release;
use App\Support\Releases\ReleaseChangelogContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseChangelogDemoContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_planned_northstar_release_contains_only_two_completed_tasks(): void
    {
        config()->set('demo.password', 'OnlyForLocalTests2026!');
        $this->seed(DemoSeeder::class);

        $project = Project::query()->where('key', 'NSTAR')->sole();
        $release = Release::query()
            ->where('project_id', $project->id)
            ->where('version', '1.1.0')
            ->sole();

        $context = app(ReleaseChangelogContext::class)->build($release);
        $source = json_decode($context, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('PLANNED', $source['release_status']);
        $this->assertTrue($source['only_completed_tasks']);
        $this->assertSame(['NSTAR-9', 'NSTAR-12'], array_column($source['tasks'], 'reference'));
        $this->assertStringContainsString('Show task activity as a readable timeline', $context);
        $this->assertStringContainsString('Add passkey sign-in to account settings', $context);
        $this->assertStringNotContainsString('Notify teammates about mentions in discussions', $context);
        $this->assertStringNotContainsString('Export project reports as CSV', $context);
        $this->assertStringNotContainsString('Speed up task list filtering', $context);
        $this->assertStringNotContainsString('Allow reactions in task discussions', $context);
    }
}
