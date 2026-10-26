import { shallowReactive } from 'vue';

export type UiLocale = string;
export type Dictionary = Record<string, string>;
export const defaultLocale: UiLocale = 'en';

// Dictionaries are served by Laravel, not bundled by Vite. Updating a JSON file
// on the server does not require rebuilding JavaScript.
export const messages = shallowReactive<Record<string, Dictionary>>({});
const loading = new Map<string, Promise<void>>();

export function normalizeLocale(locale: unknown): UiLocale {
    return typeof locale === 'string' && /^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/.test(locale)
        ? locale
        : defaultLocale;
}

export async function loadLocale(locale: string): Promise<void> {
    const code = normalizeLocale(locale);
    if (messages[code]) return;
    if (loading.has(code)) return loading.get(code);

    const pending = (async () => {
        const response = await fetch(`/ui-translations/${encodeURIComponent(code)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`Language ${code} unavailable`);
        const data: unknown = await response.json();
        if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Invalid language dictionary');
        const entries = Object.entries(data);
        if (entries.some(([key, value]) => typeof key !== 'string' || typeof value !== 'string')) {
            throw new Error('Invalid language dictionary');
        }
        messages[code] = Object.fromEntries(entries) as Dictionary;
    })().finally(() => loading.delete(code));

    loading.set(code, pending);
    return pending;
}

export async function preloadLocale(locale: unknown): Promise<void> {
    // English always supplies fallback strings when a translated key is missing.
    const requested = normalizeLocale(locale);
    await Promise.allSettled([loadLocale('en'), ...(requested !== 'en' ? [loadLocale(requested)] : [])]);
}

export function translate(key: string, locale: unknown): string {
    const selected = messages[normalizeLocale(locale)];
    const english = messages.en;
    return selected?.[key] || english?.[key] || key;
}
