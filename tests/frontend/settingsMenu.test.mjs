import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const menu = readFileSync(new URL('../../resources/js/components/UserMenuContent.vue', import.meta.url), 'utf8');
const layout = readFileSync(new URL('../../resources/js/layouts/settings/Layout.vue', import.meta.url), 'utf8');
const security = readFileSync(new URL('../../resources/js/pages/settings/Security.vue', import.meta.url), 'utf8');
const sidebarMenu = readFileSync(new URL('../../resources/js/components/NavUser.vue', import.meta.url), 'utf8');
const header = readFileSync(new URL('../../resources/js/components/AppHeader.vue', import.meta.url), 'utf8');

test('account menu has two direct links with descriptions and a larger user header', () => {
    assert.match(menu, /v-for="item in links"/);
    for (const key of ['profile', 'security']) {
        assert.ok(menu.includes(`key: '${key}'`));
        assert.ok(menu.includes(`t('settings.${key}')`));
        assert.ok(menu.includes(`t('nav.${key}Hint')`));
    }
    assert.match(menu, /:href="item.href"/);
    assert.match(menu, /:aria-current=/);
    assert.match(menu, /data-test="logout-button"/);
    assert.match(menu, /Avatar class="size-11/);
    assert.match(sidebarMenu, /w-80/);
    assert.match(header, /w-80/);
});

test('appearance and language offer radio selection in nested submenus', () => {
    assert.equal((menu.match(/<DropdownMenuSub>/g) ?? []).length, 2);
    assert.equal((menu.match(/<DropdownMenuRadioGroup/g) ?? []).length, 2);
    for (const value of ['light', 'dark', 'system']) {
        assert.ok(menu.includes(`value="${value}"`));
    }
    assert.match(menu, /page\.props\.locales/);
    assert.match(menu, /localeOptions/);
    assert.match(menu, /:disabled="localeSaving"/);
    assert.match(menu, /LocaleController\.update\.url\(\)/);
    assert.match(menu, /from_menu: true/);
    assert.match(menu, /updateAppearance\(value/);
});

test('settings pages keep their centered standalone routes without nested navigation', () => {
    assert.match(layout, /max-w-\[720px\]/);
    assert.doesNotMatch(layout, /<aside|sidebarNavItems|settings\.title/);
    assert.match(security, /ManageTwoFactor/);
    assert.match(security, /ManagePasskeys/);
});
