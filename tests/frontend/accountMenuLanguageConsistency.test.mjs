import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const menu = readFileSync(new URL('../../resources/js/components/UserMenuContent.vue', import.meta.url), 'utf8');
const controller = readFileSync(new URL('../../app/Http/Controllers/Settings/LocaleController.php', import.meta.url), 'utf8');

test('language is selected only from the real current page locale', () => {
    assert.match(menu, /:model-value="locale"/);
    assert.doesNotMatch(menu, /pendingLocale/);
    assert.match(menu, /localeSaving\.value = true/);
});

test('menu preloads language and uses JSON save with CSRF and credential cookies', () => {
    assert.match(menu, /await loadLocale\(value\)/);
    assert.match(menu, /await fetch\(LocaleController\.update\.url\(\), \{/);
    assert.match(menu, /'X-CSRF-TOKEN': csrfToken/);
    assert.match(menu, /credentials: 'same-origin'/);
    assert.match(menu, /'Accept': 'application\/json'/);
});

test('reload happens only after server and account confirm language', () => {
    assert.match(menu, /saved\.locale !== value \|\| saved\.accountLocale !== value/);
    assert.match(menu, /window\.location\.reload\(\)/);
    assert.match(controller, /\$request->expectsJson\(\)/);
    assert.match(controller, /'accountLocale' => \$request->user\(\)->fresh\(\)\?->locale/);
    assert.match(controller, /return \$redirect->withCookie\(\$localeCookie\)/);
});
