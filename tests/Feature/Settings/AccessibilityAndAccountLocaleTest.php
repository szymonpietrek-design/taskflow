<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityAndAccountLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_user_sees_polish_feedback_on_redirect_to_login(): void
    {
        $user = User::factory()->create(['locale' => 'pl', 'disabled_at' => now()]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Twoje konto zostało zablokowane. Skontaktuj się z administratorem.',
            ]);

        $this->assertGuest();
    }

    public function test_disabled_user_sees_english_feedback_when_english_is_selected(): void
    {
        $user = User::factory()->create(['locale' => 'en', 'disabled_at' => now()]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Your account has been disabled. Contact an administrator.',
            ]);

        $this->assertGuest();
    }

    public function test_shared_accessibility_labels_use_installed_language_files(): void
    {
        $components = [
            'resources/js/components/ui/sheet/SheetContent.vue' => 'ui.close',
            'resources/js/components/ui/dialog/DialogContent.vue' => 'ui.close',
            'resources/js/components/ui/dialog/DialogScrollContent.vue' => 'ui.close',
            'resources/js/components/ui/dialog/DialogFooter.vue' => 'ui.close',
            'resources/js/components/ui/sidebar/Sidebar.vue' => 'ui.mobileSidebarDescription',
            'resources/js/components/ui/sidebar/SidebarTrigger.vue' => 'ui.toggleSidebar',
            'resources/js/components/ui/breadcrumb/BreadcrumbEllipsis.vue' => 'ui.more',
        ];

        foreach ($components as $file => $key) {
            $source = file_get_contents(base_path($file));
            $this->assertStringContainsString("t('{$key}')", $source, $file);
            $this->assertStringContainsString('useI18n()', $source, $file);
        }

        $english = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $polish = json_decode(file_get_contents(lang_path('pl.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['ui.close', 'ui.sidebar', 'ui.mobileSidebarDescription', 'ui.toggleSidebar', 'ui.more'] as $key) {
            $this->assertArrayHasKey($key, $english);
            $this->assertArrayHasKey($key, $polish);
            $this->assertNotSame('', trim($english[$key]));
            $this->assertNotSame('', trim($polish[$key]));
        }
        $this->assertSame('Zamknij', $polish['ui.close']);
        $this->assertSame('Więcej', $polish['ui.more']);
    }
}
