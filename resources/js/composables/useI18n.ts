import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { messages, normalizeLocale, translate } from '@/i18n';

// Reverse mapping keeps previously used English literals translatable, while
// named label and breadcrumb keys remain explicit and independent of the source text.
export function useI18n() {
    const page = usePage();
    const locale = computed(() => normalizeLocale(page.props.locale));

    const t = (key: string): string => translate(key, locale.value);

    const englishLabelKeys = computed(() => new Map<string, string>(
        Object.entries(messages.en ?? {}).map(([key, value]) => [value, key]),
    ));

    const translateLiteral = (label: string): string => {
        const key = `navigation.${label}`;
        if (messages.en?.[key]) return t(key);
        const inferred = englishLabelKeys.value.get(label);
        return inferred ? t(inferred) : label;
    };
    const displayLabel = (label: string): string => {
        const key = `labels.${label}`;
        return messages.en?.[key] ? t(key) : translateLiteral(label);
    };
    const countLabel = (kind: 'task' | 'entry' | 'workspace' | 'member', count: number): string => {
        const category = new Intl.PluralRules(locale.value).select(count);
        const form = category === 'one' ? 'one' : category === 'few' ? 'few' : 'many';
        return t(`count.${kind}.${form}`).replace('{count}', String(count));
    };
    const formatText = (key: string, values: Record<string, string | number>): string =>
        t(key).replace(/\{(\w+)\}/g, (_, name: string) => String(values[name] ?? `{${name}}`));

    return { locale, t, displayLabel, translateLiteral, formatText, countLabel };
}
