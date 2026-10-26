<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendTranslationCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_errors_and_toasts_have_polish_and_english_translations(): void
    {
        $keys = [
            'Select an active user.',
            'This user already belongs to this project.',
            'Project member added.',
            'Project member removed.',
            'A file referenced in the message must remain attached.',
            'Remove the image or link from the message before detaching this file.',
            'Too much VCS activity for one AI report. Choose a shorter period.',
            'VCS source is only supported for unfiltered project or technical reports.',
            'AI reported more commits or pull requests than exist in the selected evidence. Regenerate the report.',
            'You cannot disable your own account.',
            'At least one active system administrator is required.',
            'Provide the GitHub App Client Secret.',
            'A repository is already linked. Disconnect it first.',
            'Could not refresh reviews. Please try again.',
            'AI generation failed. Check the provider configuration or try again later.',
            'Daily limit: 8 hours per user across all tasks and projects. Remaining on this date: :remaining.',
        ];

        foreach ($keys as $key) {
            app()->setLocale('en');
            $this->assertSame($key, __($key), "English missing: {$key}");
            app()->setLocale('pl');
            $polish = __($key);
            $this->assertNotSame($key, $polish, "Polish missing: {$key}");
            $this->assertNotSame('', trim($polish));
        }

        app()->setLocale('pl');
        $this->assertStringContainsString('2h 30m', __('Daily limit: 8 hours per user across all tasks and projects. Remaining on this date: :remaining.', [
            'remaining' => '2h 30m',
        ]));
    }

    public function test_polish_admin_receives_translated_account_protection_error(): void
    {
        $admin = User::factory()->create(['locale' => 'pl', 'is_system_admin' => true]);

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status", ['disabled' => true])
            ->assertSessionHasErrors([
                'disabled' => 'Nie możesz dezaktywować własnego konta.',
            ]);

        $this->assertNull($admin->fresh()->disabled_at);
    }
}
