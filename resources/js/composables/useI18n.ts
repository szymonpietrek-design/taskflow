import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { messages, normalizeLocale } from '@/i18n';

// Resolve server-rendered English labels without changing the stored status values.
const englishLabelKeys = new Map<string, string>(Object.entries(messages.en).map(([key, value]) => [value, key]));

export function useI18n() {
    const page = usePage();
    const locale = computed(() => normalizeLocale(page.props.locale));

    const t = (key: string): string => {
        const current = messages[locale.value] as Record<string, string>;
        const fallback = messages.en as Record<string, string>;

        return current[key] ?? fallback[key] ?? key;
    };

    const translateLiteral = (label: string): string => {
        if (locale.value !== 'pl') return label;
        const navigation: Record<string, string> = {
            Projects: 'fix.breadcrumb_projects', Workspaces: 'fix.breadcrumb_workspaces',
            Tasks: 'fix.breadcrumb_tasks', Releases: 'fix.breadcrumb_releases',
            Reports: 'fix.breadcrumb_reports', Worklogs: 'fix.breadcrumb_worklogs',
            Dashboard: 'fix.breadcrumb_dashboard', 'Project statuses': 'fix.statuses',
            'Task statuses': 'fix.statuses',
        };
        const key = navigation[label] ?? englishLabelKeys.get(label);
        return key ? t(key) : label;
    };
    const displayLabel = (label: string): string => {
        if (locale.value !== 'pl') return label;
        const labels: Record<string, string> = {
            Bug: 'Błąd', Feature: 'Funkcjonalność', Task: 'Zadanie',
            Low: 'Niski', Normal: 'Normalny', High: 'Wysoki', Urgent: 'Pilny',
            Application: 'Aplikacja', Standard: 'Standardowy',
            Workspace: 'Przestrzeń', Private: 'Prywatny',
            Todo: 'Do zrobienia', 'In progress': 'W trakcie', Done: 'Zrobione', Canceled: 'Anulowane',
        };
        return labels[label] ?? label;
    };
    const countLabel = (kind: 'task' | 'entry' | 'workspace' | 'member', count: number): string => {
        const group = locale.value === 'pl'
            ? new Intl.PluralRules('pl-PL').select(count)
            : (count === 1 ? 'one' : 'many');
        const form = group === 'one' ? 'one' : group === 'few' ? 'few' : 'many';
        return t(`count.${kind}.${form}`).replace('{count}', String(count));
    };
    const formatText = (key: string, values: Record<string, string | number>) =>
        t(key).replace(/\{(\w+)\}/g, (_, name: string) => String(values[name] ?? `{${name}}`));
    return { locale, t, displayLabel, translateLiteral, formatText, countLabel };
}
