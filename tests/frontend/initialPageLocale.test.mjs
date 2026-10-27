import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readInitialPageLocale } from '../../resources/js/i18n/initialPageLocale.ts';

function pageDocument({ script = null, attribute = null } = {}) {
    return {
        querySelector(selector) {
            assert.equal(selector, 'script[data-page="app"][type="application/json"]');
            return script === null ? null : { textContent: script };
        },
        getElementById(id) {
            assert.equal(id, 'app');
            return attribute === null ? null : { getAttribute: (name) => {
                assert.equal(name, 'data-page');
                return attribute;
            } };
        },
    };
}

test('Inertia 3: reads Polish locale from initial JSON script', () => {
    const doc = pageDocument({ script: JSON.stringify({ component: 'Dashboard', props: { locale: 'pl' } }) });
    assert.equal(readInitialPageLocale(doc), 'pl');
});

test('Inertia 3: reads English locale', () => {
    assert.equal(readInitialPageLocale(pageDocument({ script: '{"props":{"locale":"en"}}' })), 'en');
});

test('Inertia 3: script takes precedence over legacy attribute', () => {
    const doc = pageDocument({ script: '{"props":{"locale":"pl"}}', attribute: '{"props":{"locale":"en"}}' });
    assert.equal(readInitialPageLocale(doc), 'pl');
});

test('Inertia 2: supports the legacy #app data-page attribute', () => {
    assert.equal(readInitialPageLocale(pageDocument({ attribute: '{"props":{"locale":"pl"}}' })), 'pl');
});

test('Missing or broken initial payload defaults to English', () => {
    assert.equal(readInitialPageLocale(pageDocument()), 'en');
    assert.equal(readInitialPageLocale(pageDocument({ script: '{bad json' })), 'en');
    assert.equal(readInitialPageLocale(pageDocument({ script: '{"props":{"locale":null}}' })), 'en');
});
