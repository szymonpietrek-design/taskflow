import { createInertiaApp, router } from '@inertiajs/vue3';
import { preloadLocale } from '@/i18n';
import { readInitialPageLocale } from '@/i18n/initialPageLocale';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Inertia 3 places the initial page JSON in a script tag, not on #app.
// Preload the actual account language *before* Vue mounts, so the interface
// doesn't silently fall back to English on a full page load.
const initialLocale = readInitialPageLocale(document);

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
