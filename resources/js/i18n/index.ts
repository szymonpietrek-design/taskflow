import en from './en';
import pl from './pl';

export const messages = { en, pl } as const;

export type UiLocale = keyof typeof messages;

export const defaultLocale: UiLocale = 'en';

export function normalizeLocale(locale: unknown): UiLocale {
    return typeof locale === 'string' && locale in messages
        ? (locale as UiLocale)
        : defaultLocale;
}
