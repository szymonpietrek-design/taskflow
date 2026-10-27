import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/components/UserMenuContent.vue', import.meta.url), 'utf8');
const en = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8'));
const pl = JSON.parse(readFileSync(new URL('../../lang/pl.json', import.meta.url), 'utf8'));

test('appearance submenu has icon header, descriptions and a trailing check', () => {
    assert.match(source, /<DropdownMenuSubContent[\s\S]*?<Palette class="size-4/);
    for (const key of ['appearanceLightHint', 'appearanceDarkHint', 'appearanceSystemHint']) {
        assert.ok(source.includes(`t('nav.${key}')`));
    }
    assert.equal((source.match(/<template #indicator-icon>/g) ?? []).length, 4);
    assert.equal((source.match(/<Check class="size-4"/g) ?? []).length, 4);
    assert.equal((source.match(/\[&>span:first-child\]:right-3/g) ?? []).length, 4);
    for (const mode of ['light', 'dark', 'system']) assert.ok(source.includes(`value="${mode}"`));
    assert.match(source, /:model-value="appearance"/);
    assert.match(source, /@update:model-value="chooseAppearance"/);
});

test('language submenu uses installed languages, descriptions and right-side check', () => {
    assert.match(source, /<DropdownMenuSubContent[\s\S]*?<Languages class="size-4/);
    assert.match(source, /v-for="option in localeOptions"/);
    assert.match(source, /t\('nav\.languageOptionHint'\)/);
    assert.match(source, /:model-value="locale"/);
    assert.match(source, /@update:model-value="chooseLanguage"/);
    assert.match(source, /:disabled="localeSaving"/);
});

test('new hints exist in identical EN/PL dictionaries', () => {
    assert.deepEqual(Object.keys(en), Object.keys(pl));
    for (const dict of [en, pl]) {
        for (const key of ['nav.appearanceLightHint', 'nav.appearanceDarkHint', 'nav.appearanceSystemHint', 'nav.languageOptionHint']) {
            assert.ok(typeof dict[key] === 'string' && dict[key].trim().length > 0, key);
        }
    }
});


test('checked submenu items use only the trailing check, while hover styling remains available', () => {
    assert.doesNotMatch(source, /data-\[state=checked\]:bg-accent/);
    assert.equal((source.match(/<DropdownMenuRadioItem/g) ?? []).length, 4); // 3 appearance choices and 1 dynamic language choice
    assert.equal((source.match(/<template #indicator-icon>/g) ?? []).length, 4);
    assert.equal((source.match(/<Check class="size-4"/g) ?? []).length, 4);
    assert.equal((source.match(/\[&>span:first-child\]:right-3/g) ?? []).length, 4);
});
