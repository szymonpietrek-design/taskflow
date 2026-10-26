import { createInertiaApp, router } from '@inertiajs/vue3';
import { preloadLocale } from '@/i18n';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Load the first dictionary before Vue mounts, avoiding flashes of English.
// Inertia stores its initial page JSON in the root element's data-page attribute.
const initialPage = document.getElementById('app')?.getAttribute('data-page');
let initialLocale: unknown = 'en';
if (initialPage) {
    try {
        const parsed = JSON.parse(initialPage) as { props?: { locale?: unknown } };
        initialLocale = parsed.props?.locale;
    } catch { /* retain English fallback */ }
}

router.on('success', (event) => {
    void preloadLocale(event.detail.page.props.locale);
});

void preloadLocale(initialLocale).then(() => createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
}));

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
