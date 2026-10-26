<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;

class FrontendLanguageCoverageTest extends TestCase
{
    public function test_installed_english_and_polish_have_matching_keys_and_interpolation_variables(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $polish = json_decode(file_get_contents(lang_path('pl.json')), true, flags: JSON_THROW_ON_ERROR);
        $englishKeys = array_keys($english);
        $polishKeys = array_keys($polish);
        sort($englishKeys);
        sort($polishKeys);
        $this->assertSame($englishKeys, $polishKeys);

        $placeholders = static function (string $text): array {
            preg_match_all('/\{[a-zA-Z0-9_]+\}|(?<![a-zA-Z0-9:]):[a-zA-Z][a-zA-Z0-9_]*/', $text, $matches);
            $values = array_unique($matches[0]);
            sort($values);

            return array_values($values);
        };
        foreach ($englishKeys as $key) {
            $this->assertIsString($english[$key], "English value for {$key}");
            $this->assertIsString($polish[$key], "Polish value for {$key}");
            $this->assertSame($placeholders($english[$key]), $placeholders($polish[$key]), "Interpolation mismatch: {$key}");
        }
    }

    public function test_git_interface_and_auth_labels_use_translation_keys(): void
    {
        $files = [
            'resources/js/components/AppSidebar.vue' => 'nav.adminGitIntegrations',
            'resources/js/components/NavProjects.vue' => 'nav.gitIntegrations',
            'resources/js/pages/projects/integrations/GitHub.vue' => 'github.legacyAuthorsPending',
            'resources/js/pages/projects/integrations/GitHubPullRequest.vue' => 'github.pr.snapshotEvidence',
            'resources/js/pages/auth/ConfirmPassword.vue' => 'auth.confirmWithPasskey',
            'resources/js/components/ui/sidebar/SidebarRail.vue' => 'ui.toggleSidebar',
            'resources/js/components/ui/spinner/Spinner.vue' => 'ui.loading',
            'resources/js/pages/releases/Show.vue' => 'releases.changelogPlaceholder',
        ];
        foreach ($files as $path => $translationKey) {
            $content = file_get_contents(base_path($path));
            $this->assertStringContainsString($translationKey, $content, $path);
            $this->assertStringNotContainsString("locale === 'pl'", $content, $path);
            $this->assertStringNotContainsString("locale.value === 'pl'", $content, $path);
        }
    }
}
