/**
 * Read the locale from Inertia's initial page before Vue mounts.
 * Inertia 3 embeds the page JSON in a dedicated <script> element.
 * The data-page attribute is retained as a backwards-compatible fallback.
 */
export function readInitialPageLocale(doc: Document): string {
    const scriptPage = doc.querySelector<HTMLScriptElement>(
        'script[data-page="app"][type="application/json"]',
    )?.textContent;
    const attributePage = doc.getElementById('app')?.getAttribute('data-page');
    const rawPage = scriptPage?.trim() || attributePage;

    if (!rawPage) return 'en';

    try {
        const page: unknown = JSON.parse(rawPage);
        if (!page || typeof page !== 'object' || !('props' in page)) {
            return 'en';
        }

        const props = (page as { props?: unknown }).props;
        if (!props || typeof props !== 'object' || !('locale' in props)) {
            return 'en';
        }

        const locale = (props as { locale?: unknown }).locale;
        return typeof locale === 'string' ? locale : 'en';
    } catch {
        return 'en';
    }
}
