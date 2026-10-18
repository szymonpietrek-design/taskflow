<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectLogoTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Studio');
        $project = app(CreateProject::class)->handle($owner, $workspace, 'Platform', 'PLAT');

        return [$owner, $project];
    }

    public function test_logo_can_be_uploaded_viewed_replaced_and_removed(): void
    {
        Storage::fake('local');
        [$owner, $project] = $this->fixtures();
        $this->actingAs($owner)->post(route('projects.logo.store', $project), [
            'logo' => UploadedFile::fake()->image('first.png', 128, 128),
        ])->assertSessionHasNoErrors();

        $first = $project->fresh()->logo_path;
        $this->assertNotNull($first);
        Storage::disk('local')->assertExists($first);
        $this->actingAs($owner)->get(route('workspaces.index'))
            ->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('projectsByWorkspace.'. $project->workspace_id .'.0.logo_url', \App\Support\Projects\ProjectLogo::url($project->fresh()))
                ->etc());
        $this->actingAs($owner)->get(route('projects.logo.show', $project))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($owner)->post(route('projects.logo.store', $project), [
            'logo' => UploadedFile::fake()->image('second.png', 128, 128),
        ])->assertSessionHasNoErrors();
        $second = $project->fresh()->logo_path;
        $this->assertNotEquals($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->actingAs($owner)->delete(route('projects.logo.destroy', $project))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($second);
        $this->assertNull($project->fresh()->logo_path);
        $this->actingAs($owner)->get(route('projects.logo.show', $project))->assertNotFound();
    }

    public function test_logo_rejects_unsupported_files_and_oversized_images(): void
    {
        Storage::fake('local');
        [$owner, $project] = $this->fixtures();
        $this->actingAs($owner)->post(route('projects.logo.store', $project), [
            'logo' => UploadedFile::fake()->create('file.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('logo');
        $this->actingAs($owner)->post(route('projects.logo.store', $project), [
            'logo' => UploadedFile::fake()->image('huge.png', 100, 100)->size(2049),
        ])->assertSessionHasErrors('logo');
        $this->assertNull($project->fresh()->logo_path);
    }

    public function test_private_logo_is_not_exposed_to_outsiders_and_read_only_cannot_modify(): void
    {
        Storage::fake('local');
        [$owner, $project] = $this->fixtures();
        $this->actingAs($owner)->post(route('projects.logo.store', $project), [
            'logo' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])->assertSessionHasNoErrors();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('projects.logo.show', $project))->assertForbidden();
        $this->actingAs($outsider)->post(route('projects.logo.store', $project), [
            'logo' => UploadedFile::fake()->image('other.png', 64, 64),
        ])->assertForbidden();
        $this->actingAs($outsider)->delete(route('projects.logo.destroy', $project))->assertForbidden();

        $project->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner)->get(route('projects.logo.show', $project))->assertOk();
        $this->actingAs($owner)->delete(route('projects.logo.destroy', $project))->assertForbidden();
    }
}
