<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    ArrowLeft,
    ClipboardList,
    FileText,
    LoaderCircle,
    MessageCircle,
    Activity,
    Trash2,
    Clock3,
    GitBranch,
    Users,
    ListTodo,
    Sparkles,
    Pencil,
    Save,
    CheckCircle2,
    RotateCcw,
    Download,
    Share2,
    Copy,
    ExternalLink,
    Link2Off,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { formatReportPeriod, formatReportTimestamp } from '@/lib/reportDates';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Report = {
    id: number;
    title: string;
    period_start: string;
    period_end: string;
    format: string;
    contributor_names: string[] | null;
    sources: string[];
    is_generated: boolean;
    generated_locale: string | null;
    generated_at: string | null;
    published_at: string | null;
    edited_at: string | null;
    updated_version: string | null;
    body: string | null;
    created_by: string | null;
    created_at: string | null;
};
type WorklogGroup = {
    entries: number;
    total_minutes: number;
    creative_minutes: number;
};
type WorklogBreakdown = {
    entries: number;
    total_minutes: number;
    creative_entries: number;
    creative_minutes: number;
    by_user: (WorklogGroup & { user_id: number | null; name: string })[];
    by_task: (WorklogGroup & {
        task_id: number;
        number: number;
        title: string;
    })[];
};
const props = defineProps<{
    project: { id: number; name: string; key: string };
    report: Report;
    source_counts: {
        tasks: number | null;
        messages: number | null;
        activity: number | null;
        worklogs: WorklogBreakdown | null;
        vcs: { commits: number; pull_requests: number } | null;
    };
    can_delete: boolean;
    can_generate: boolean;
    can_edit: boolean;
    can_share: boolean;
    share_url: string | null;
    editable_content: string | null;
    ai_configured: boolean;
    default_ai_language: string;
    report_html: string | null;
}>();
const worklogDetails = computed<WorklogBreakdown>(
    () =>
        props.source_counts.worklogs ?? {
            entries: 0,
            total_minutes: 0,
            creative_entries: 0,
            creative_minutes: 0,
            by_user: [],
            by_task: [],
        },
);
const baseUrl = `/projects/${props.project.id}/reports`;
const languageOptions = [
    { value: 'en', label: 'English' },
    { value: 'pl', label: 'Polski' },
    { value: 'de', label: 'Deutsch' },
    { value: 'fr', label: 'Français' },
    { value: 'es', label: 'Español' },
    { value: 'it', label: 'Italiano' },
    { value: 'pt', label: 'Português' },
    { value: 'pt-BR', label: 'Português (Brasil)' },
    { value: 'nl', label: 'Nederlands' },
    { value: 'uk', label: 'Українська' },
    { value: 'ja', label: '日本語' },
    { value: 'ko', label: '한국어' },
    { value: 'zh-Hans', label: '简体中文' },
    { value: 'zh-Hant', label: '繁體中文' },
] as const;
const defaultLanguage =
    props.report.generated_locale ?? props.default_ai_language;
const isPresetLanguage = (code: string): boolean =>
    languageOptions.some((option) => option.value === code);
const selectedLanguage = ref(
    isPresetLanguage(defaultLanguage) ? defaultLanguage : '__other',
);
const customLanguage = ref(
    isPresetLanguage(defaultLanguage) ? '' : defaultLanguage,
);
const outputLanguage = computed(() =>
    selectedLanguage.value === '__other'
        ? customLanguage.value.trim()
        : selectedLanguage.value,
);
const isValidLanguage = computed(
    () =>
        /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i.test(outputLanguage.value) &&
        outputLanguage.value.length <= 35,
);
const generateForm = useForm({ language: defaultLanguage });
function languageLabel(code: string | null): string {
    if (!code) return '';
    return (
        languageOptions.find((language) => language.value === code)?.label ??
        code
    );
}
const isEditing = ref(false);
const contentForm = useForm({
    content: props.editable_content ?? '',
    version: props.report.updated_version ?? '',
});
watch(
    () => props.report.updated_version,
    () => {
        if (!isEditing.value) {
            contentForm.content = props.editable_content ?? '';
            contentForm.version = props.report.updated_version ?? '';
        }
    },
);
function startEditing() {
    if (
        !props.can_edit ||
        props.report.published_at ||
        props.editable_content === null
    )
        return;
    contentForm.clearErrors();
    contentForm.content = props.editable_content;
    contentForm.version = props.report.updated_version ?? '';
    isEditing.value = true;
}
function cancelEditing() {
    isEditing.value = false;
    contentForm.reset();
    contentForm.clearErrors();
}
function saveContent() {
    if (!contentForm.content.trim() || contentForm.processing) return;
    contentForm.put(`${baseUrl}/${props.report.id}/content`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
        onError: (errors) =>
            toast.error(errors.content || t('audit.could_not_save_report')),
    });
}
const publicationForm = useForm({});
function changePublication(publish: boolean) {
    if (publicationForm.processing) return;
    if (
        !publish &&
        !window.confirm(
            t('audit.move_this_report_back_to_draft_it_can_then_be_edited_or_regenerated'),
        )
    )
        return;
    publicationForm.post(
        `${baseUrl}/${props.report.id}/${publish ? 'publish' : 'unpublish'}`,
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(
                    errors.publication || t('audit.could_not_update_report_status'),
                ),
        },
    );
}
const sharingForm = useForm({});
function selectLink(event: FocusEvent) {
    (event.target as HTMLInputElement | null)?.select();
}
function enableSharing() {
    if (sharingForm.processing || !props.report.published_at) return;
    if (
        !window.confirm(
            t('audit.anyone_with_this_link_will_be_able_to_read_the_published_report_enable_sharing'),
        )
    )
        return;
    sharingForm.post(`${baseUrl}/${props.report.id}/share`, {
        preserveScroll: true,
        onError: (errors) =>
            toast.error(errors.sharing || t('audit.could_not_enable_sharing')),
    });
}
function revokeSharing() {
    if (
        sharingForm.processing ||
        !window.confirm(t('audit.revoke_this_link_anyone_using_it_will_lose_access'))
    )
        return;
    sharingForm.delete(`${baseUrl}/${props.report.id}/share`, {
        preserveScroll: true,
        onError: () => toast.error(t('audit.could_not_revoke_the_link')),
    });
}
async function copyShareLink() {
    if (!props.share_url) return;
    try {
        await navigator.clipboard.writeText(props.share_url);
        toast.success(t('audit.link_copied'));
    } catch {
        toast.error(t('audit.copy_failed_select_the_link_and_copy_it_manually'));
    }
}
const generationError = ref<string | undefined>();
function generateReport() {
    if (
        !props.can_generate ||
        isEditing.value ||
        !isValidLanguage.value ||
        generateForm.processing
    )
        return;
    if (
        props.report.is_generated &&
        !window.confirm(t('audit.replace_the_current_report_with_a_new_version'))
    )
        return;
    generationError.value = undefined;
    generateForm.language = outputLanguage.value;
    generateForm.post(`${baseUrl}/${props.report.id}/generate`, {
        preserveScroll: true,
        onError: (errors) => {
            generationError.value = errors.ai_generation;
            toast.error(
                generationError.value ||
                    errors.language ||
                    t('audit.could_not_generate_the_report'),
            );
        },
        onSuccess: () => {
            generationError.value = undefined;
        },
    });
}
const deleteForm = useForm({});
function removeReport() {
    if (!window.confirm(t('audit.delete_this_report'))) return;
    deleteForm.delete(`${baseUrl}/${props.report.id}`, {
        preserveScroll: true,
    });
}
const selectedSources = [
    { key: 'tasks' as const, title: 'fix.report_source_tasks_title', icon: ClipboardList },
    { key: 'messages' as const, title: 'fix.report_source_messages_title', icon: MessageCircle },
    { key: 'activity' as const, title: 'reports.taskHistoryTitle', icon: Activity },
].filter((source) => props.report.sources.includes(source.key));
// Worklogs have their own section immediately below the source cards.
const selectedSourceCount =
    selectedSources.length + Number(props.report.sources.includes('vcs'));
function durationLabel(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    return (
        [hours ? `${hours}h` : '', remaining ? `${remaining}m` : '']
            .filter(Boolean)
            .join(' ') || '0m'
    );
}
function percentage(part: number, total: number): number {
    return total > 0 ? Math.round((part / total) * 100) : 0;
}
defineOptions({
    layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] },
});
const { t, locale, formatText, translateLiteral, countLabel } = useI18n();
const commitCountLabel = computed((): string => {
    const count = props.source_counts.vcs?.commits ?? 0;
    if (locale.value !== 'pl') return count === 1 ? 'commit' : 'commits';
    const plural = new Intl.PluralRules('pl-PL').select(count);
    return plural === 'one' ? 'commit' : plural === 'few' ? 'commity' : 'commitów';
});
</script>

<template>
    <Head :title="`${report.title} · Reports`" />
    <div class="mx-auto flex w-full min-w-0 max-w-[92rem] flex-1 flex-col gap-6 px-4 py-5 md:px-8 md:py-6 2xl:px-10">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link :href="baseUrl"
                    ><ArrowLeft class="size-4" /> {{ t('stage3.all_reports') }}</Link
                >
            </Button>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge variant="outline">{{
                    report.format === 'work_report'
                        ? t('audit.detailed_report')
                        : report.format === 'technical_report'
                            ? t('reports.technicalReport')
                            : t('reports.projectReport')
                }}</Badge>
                <Badge :variant="report.published_at ? 'default' : 'outline'">{{
                    report.published_at ? t('audit.published') : t('audit.draft')
                }}</Badge>
                <Badge
                    v-if="report.is_generated && !report.published_at"
                    variant="secondary"
                    >{{ t('stage3.generated') }}</Badge
                >
            </div>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ report.title }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ formatReportPeriod(report.period_start, report.period_end, locale)
                }}<template v-if="report.created_by">
                    · {{ formatText('audit.created_by', { name: report.created_by }) }}</template
                >
            </p>
            <p class="text-xs text-muted-foreground">
                {{ t('stage3.people') }}:
                {{
                    report.contributor_names === null
                        ? t('audit.report_all_contributors')
                        : report.contributor_names.join(', ') ||
                          t('audit.former_contributors')
                }}
            </p>
        </div>
        <Card class="gap-3 py-5">
            <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-2">
                <CardTitle class="text-base">{{ t('stage3.sources') }}</CardTitle>
                <span class="text-xs text-muted-foreground">
                    {{ formatText('audit.selected_count', { count: selectedSourceCount }) }}
                </span>
            </CardHeader>
            <CardContent class="space-y-2.5">
                <div
                    class="grid gap-2.5"
                    style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 10.5rem), 1fr));"
                >
                    <div
                        v-for="source in selectedSources"
                        :key="source.key"
                        class="flex min-w-0 flex-col justify-between gap-2 rounded-lg border bg-muted/15 p-3"
                    >
                        <div class="flex min-w-0 items-start gap-2 text-sm font-medium">
                            <component :is="source.icon" class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 leading-snug">{{ t(source.title) }}</span>
                        </div>
                        <p class="text-xl font-semibold tabular-nums">
                            {{ source_counts[source.key] ?? 0 }}
                        </p>
                    </div>
                    <div
                        v-if="report.sources.includes('vcs')"
                        class="flex min-w-0 flex-col justify-between gap-2 rounded-lg border bg-muted/15 p-3"
                    >
                        <div class="flex min-w-0 items-start gap-2 text-sm font-medium">
                            <GitBranch class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 leading-snug">{{ t('reports.gitActivityTitle') }}</span>
                        </div>
                        <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-1 text-sm">
                            <span class="font-semibold tabular-nums">{{ source_counts.vcs?.commits ?? 0 }}</span>
                            <span class="text-muted-foreground">{{ commitCountLabel }}</span>
                            <span class="px-0.5 text-muted-foreground" aria-hidden="true">·</span>
                            <span class="font-semibold tabular-nums">{{ source_counts.vcs?.pull_requests ?? 0 }}</span>
                            <span class="text-muted-foreground">PR</span>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t('audit.totals_update_as_project_data_changes') }}
                </p>
            </CardContent>
        </Card>
        <Card v-if="source_counts.worklogs" class="gap-3 py-5">
            <CardHeader class="gap-1">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Clock3 class="size-4" /> {{ t('audit.worklogs') }}
                    </CardTitle>
                    <span class="text-sm font-semibold tabular-nums">
                        {{ t('stage3.total') }}: {{ durationLabel(worklogDetails.total_minutes) }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('audit.time_recorded_for_this_project_during_the_selected_period') }}
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="min-w-0 rounded-lg border bg-muted/15 p-3">
                        <p class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Clock3 class="size-4" /> {{ t('stage3.total') }}
                        </p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">
                            {{ durationLabel(worklogDetails.total_minutes) }}
                        </p>
                    </div>
                    <div class="min-w-0 rounded-lg border bg-muted/15 p-3">
                        <p class="flex items-center gap-2 text-xs text-muted-foreground">
                            <FileText class="size-4" /> {{ t('stage3.worklogs') }}
                        </p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">{{ worklogDetails.entries }}</p>
                    </div>
                    <div class="min-w-0 rounded-lg border bg-muted/15 p-3">
                        <p class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Users class="size-4" /> {{ t('audit.contributors') }}
                        </p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">{{ worklogDetails.by_user.length }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('audit.people_with_logged_time') }}</p>
                    </div>
                    <div class="min-w-0 rounded-lg border bg-muted/15 p-3">
                        <p class="flex items-center gap-2 text-xs text-muted-foreground">
                            <ListTodo class="size-4" /> {{ t('audit.tasks_worked_on') }}
                        </p>
                        <p class="mt-1 text-xl font-semibold tabular-nums">{{ worklogDetails.by_task.length }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('audit.tasks_with_entries_in_this_period') }}</p>
                    </div>
                </div>
                <!-- Preserve the existing creative-work feature; show it as optional
                     secondary information instead of a primary metric tile. -->
                <div
                    v-if="worklogDetails.creative_entries > 0"
                    class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                >
                    <Sparkles class="size-4 shrink-0" />
                    <span class="font-medium text-foreground">{{ t('audit.creative_work') }}:</span>
                    <span class="font-semibold tabular-nums text-foreground">{{ durationLabel(worklogDetails.creative_minutes) }}</span>
                    <span>{{ formatText('audit.worklog_creative_percent', { percent: percentage(worklogDetails.creative_minutes, worklogDetails.total_minutes), count: worklogDetails.creative_entries }) }}</span>
                </div>
                <div
                    v-if="worklogDetails.entries === 0"
                    class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ t('audit.no_time_entries_for_this_project_and_reporting_period') }}
                </div>
                <div v-else class="grid items-start gap-4 lg:grid-cols-2">
                    <section class="min-w-0 space-y-2">
                        <h3 class="text-sm font-semibold">
                            {{ t('audit.time_by_contributor') }}
                        </h3>
                        <div
                            class="max-h-[26rem] space-y-3 overflow-y-auto rounded-lg border p-3"
                        >
                            <div
                                v-for="person in worklogDetails.by_user"
                                :key="person.user_id ?? 'former'"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex min-w-0 items-start justify-between gap-3 text-sm"
                                >
                                    <span
                                        class="min-w-0 truncate font-medium"
                                        :title="person.name"
                                        >{{ person.name }}</span
                                    >
                                    <span
                                        class="shrink-0 font-semibold tabular-nums"
                                        >{{
                                            durationLabel(person.total_minutes)
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="h-1.5 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary"
                                        :style="{
                                            width: `${percentage(person.total_minutes, worklogDetails.total_minutes)}%`,
                                        }"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ countLabel('entry', person.entries) }} ·
                                    {{
                                        percentage(
                                            person.total_minutes,
                                            worklogDetails.total_minutes,
                                        )
                                    }}% {{ t('fix.percent_total') }}<span
                                        v-if="person.creative_minutes"
                                    >
                                        ·
                                        {{
                                            durationLabel(
                                                person.creative_minutes,
                                            )
                                        }}
                                        {{ t('fix.worklog_creative') }}</span
                                    >
                                </p>
                            </div>
                        </div>
                    </section>
                    <section class="min-w-0 space-y-2">
                        <h3 class="text-sm font-semibold">{{ t('stage3.time_by_task') }}</h3>
                        <div
                            class="max-h-[26rem] space-y-3 overflow-y-auto rounded-lg border p-3"
                        >
                            <div
                                v-for="task in worklogDetails.by_task"
                                :key="task.task_id"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex min-w-0 items-start justify-between gap-3 text-sm"
                                >
                                    <Link
                                        :href="`/projects/${project.id}/tasks/${task.task_id}`"
                                        class="min-w-0 font-medium underline-offset-4 hover:underline"
                                    >
                                        <span class="text-muted-foreground"
                                            >{{ project.key }}-{{
                                                task.number
                                            }}</span
                                        >
                                        · {{ task.title }}
                                    </Link>
                                    <span
                                        class="shrink-0 font-semibold tabular-nums"
                                        >{{
                                            durationLabel(task.total_minutes)
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="h-1.5 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary"
                                        :style="{
                                            width: `${percentage(task.total_minutes, worklogDetails.total_minutes)}%`,
                                        }"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ countLabel('entry', task.entries) }} ·
                                    {{
                                        percentage(
                                            task.total_minutes,
                                            worklogDetails.total_minutes,
                                        )
                                    }}% {{ t('fix.percent_total') }}<span
                                        v-if="task.creative_minutes"
                                    >
                                        ·
                                        {{
                                            durationLabel(task.creative_minutes)
                                        }}
                                        {{ t('fix.worklog_creative') }}</span
                                    >
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t('audit.based_on_work_dates_totals_update_when_entries_change') }}
                </p>
            </CardContent>
        </Card>
        <div
            class="grid min-w-0 items-start gap-6"
            :class="
                can_generate ||
                (can_share && !!report.published_at) ||
                report.is_generated
                    ? 'xl:grid-cols-[minmax(0,1fr)_18rem]'
                    : ''
            "
        >
            <Card class="min-w-0">
                <CardHeader class="gap-2">
                    <CardTitle class="flex items-center gap-2 text-base"
                        ><FileText class="size-4" /> {{ t('stage3.report') }}</CardTitle
                    >
                    <p
                        v-if="report.is_generated && report.generated_at"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('audit.generated') }} {{ formatReportTimestamp(report.generated_at, locale)
                        }}<template v-if="report.generated_locale">
                            ·
                            {{
                                languageLabel(report.generated_locale)
                            }}</template
                        ><template v-if="report.edited_at">
                            · {{ t('audit.edit') }}
                            {{
                                formatReportTimestamp(report.edited_at, locale)
                            }}</template
                        >
                    </p>
                    <p
                        v-if="report.published_at"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('fix.published') }}
                        {{ formatReportTimestamp(report.published_at, locale) }}
                    </p>
                    <p
                        v-if="
                            report.format === 'work_report' &&
                            report.edited_at &&
                            !isEditing
                        "
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('audit.this_document_has_been_edited_source_worklogs_and_the_live_statistics_above_are_unchanged') }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="
                            can_edit &&
                            report.is_generated &&
                            !report.published_at
                        "
                        class="flex flex-wrap items-center gap-2 border-b pb-4"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="
                                    !isEditing &&
                                    !report.published_at &&
                                    editable_content !== null
                                "
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="startEditing"
                            >
                                <Pencil class="size-4" /> {{ t('audit.edit') }}
                            </Button>
                            <template v-if="isEditing">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="contentForm.processing"
                                    @click="cancelEditing"
                                    >{{ t('stage3.cancel') }}</Button
                                >
                                <Button
                                    type="button"
                                    size="sm"
                                    :disabled="
                                        contentForm.processing ||
                                        !contentForm.content.trim()
                                    "
                                    @click="saveContent"
                                >
                                    <LoaderCircle
                                        v-if="contentForm.processing"
                                        class="size-4 animate-spin"
                                    /><Save v-else class="size-4" /> {{ t('audit.save_changes') }}
                                </Button>
                            </template>
                        </div>
                    </div>
                    <div v-if="isEditing" class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            {{ t('audit.edit_the_full_report_as_markdown_including_headings_table_rows_and_totals_changes_affect_t') }}
                        </p>
                        <Textarea
                            v-model="contentForm.content"
                            class="min-h-[26rem] resize-y font-mono text-sm leading-6"
                            :maxlength="50000"
                            :aria-label="t('stage3.report_content')"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ formatText('audit.report_characters', { count: contentForm.content.length.toLocaleString(locale === 'pl' ? 'pl-PL' : 'en-US') }) }}
                        </p>
                        <InputError
                            :message="
                                contentForm.errors.content ||
                                contentForm.errors.version
                            "
                        />
                    </div>
                    <div
                        v-else-if="report.is_generated && report_html"
                        class="report-markdown w-full min-w-0 overflow-x-auto text-sm leading-6 break-words sm:text-[0.9375rem] sm:leading-7 [&_a]:underline [&_h1]:mb-4 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mt-6 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:my-3 [&_h3]:font-semibold [&_li]:my-1 [&_li]:ml-5 [&_ol]:my-3 [&_ol]:list-decimal [&_p]:mb-4 [&_strong]:font-semibold [&_table]:my-5 [&_table]:w-full [&_table]:min-w-[42rem] [&_table]:border-collapse [&_tbody_tr:nth-child(even)]:bg-muted/20 [&_td]:border [&_td]:p-2 [&_td]:align-top [&_th]:border [&_th]:p-2 [&_th]:text-left [&_thead]:bg-muted/60 [&_ul]:my-3 [&_ul]:list-disc"
                        v-html="report_html"
                    ></div>
                    <div
                        v-else
                        class="rounded-lg border border-dashed px-4 py-10 text-center"
                    >
                        <FileText
                            class="mx-auto mb-3 size-7 text-muted-foreground"
                        />
                        <p class="text-sm font-medium">{{ t('stage3.no_report_content_yet') }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t('audit.generate_a_report_from_the_selected_sources') }}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <div
                v-if="
                    can_generate ||
                    (can_share && !!report.published_at) ||
                    report.is_generated
                "
                class="min-w-0 space-y-4 xl:sticky xl:top-6"
            >
                <Card v-if="can_generate" class="min-w-0">
                    <CardHeader class="gap-1">
                        <CardTitle class="flex items-center gap-2 text-base"
                            ><Sparkles class="size-4" /> {{ t('stage3.generate') }}</CardTitle
                        >
                        <p class="text-sm text-muted-foreground">
                            {{
                                report.format === 'work_report'
                                    ? t('audit.generate_a_work_summary_and_time_register')
                                    : report.format === 'technical_report'
                                        ? t('reports.generateTechnicalReport')
                                        : t('audit.summarize_progress_from_the_selected_sources')
                            }}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            class="space-y-4"
                            @submit.prevent="generateReport"
                        >
                            <div class="space-y-1.5">
                                <label
                                    for="report-ai-language"
                                    class="text-sm font-medium"
                                    >{{ t('stage3.language') }}</label
                                >
                                <Select v-model="selectedLanguage">
                                    <SelectTrigger
                                        id="report-ai-language"
                                        class="w-full"
                                        ><SelectValue
                                            :placeholder="t('audit.choose_a_language')"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="language in languageOptions"
                                            :key="language.value"
                                            :value="language.value"
                                            >{{ language.label }}</SelectItem
                                        >
                                        <SelectItem value="__other"
                                            >{{ t('stage3.other_language') }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div
                                v-if="selectedLanguage === '__other'"
                                class="space-y-1.5"
                            >
                                <label
                                    for="report-ai-custom-language"
                                    class="text-sm font-medium"
                                    >{{ t('stage3.language_code') }}</label
                                >
                                <Input
                                    id="report-ai-custom-language"
                                    v-model="customLanguage"
                                    maxlength="35"
                                    placeholder="e.g. sv, hi, zh-Hant-TW"
                                />
                            </div>
                            <InputError
                                :message="
                                    generateForm.errors.language ||
                                    generationError
                                "
                            />
                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="
                                    !can_generate ||
                                    isEditing ||
                                    generateForm.processing ||
                                    !ai_configured ||
                                    !isValidLanguage
                                "
                            >
                                <LoaderCircle
                                    v-if="generateForm.processing"
                                    class="size-4 animate-spin"
                                />
                                <Sparkles v-else class="size-4" />
                                {{
                                    generateForm.processing
                                        ? t('audit.generating')
                                        : report.is_generated
                                          ? t('audit.regenerate_report')
                                          : t('audit.generate_report')
                                }}
                            </Button>
                            <p
                                v-if="!ai_configured"
                                class="text-xs text-muted-foreground"
                            >
                                {{ t('audit.report_generation_is_not_configured') }}
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                {{
                                    report.published_at
                                        ? t('audit.move_this_report_to_draft_before_regenerating_it')
                                        : t('audit.selected_project_data_is_sent_to_your_ai_provider_review_the_result_before_sharing')
                                }}
                            </p>
                        </form>
                    </CardContent>
                </Card>
                <Card v-if="can_share && report.published_at" class="min-w-0">
                    <CardHeader class="gap-1">
                        <CardTitle class="flex items-center gap-2 text-base"
                            ><Share2 class="size-4" /> {{ t('stage3.share') }}</CardTitle
                        >
                        <p class="text-sm text-muted-foreground">
                            {{ t('audit.anyone_with_the_link_can_view_the_published_report') }}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <template v-if="share_url">
                            <Input
                                :model-value="share_url"
                                readonly
                                :aria-label="t('audit.public_report_link')"
                                class="w-full text-xs"
                                @focus="selectLink"
                            />
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    type="button"
                                    @click="copyShareLink"
                                    ><Copy class="size-4" /> {{ t('stage3.copy_link') }}</Button
                                >
                                <Button size="sm" variant="outline" as-child
                                    ><a
                                        :href="share_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        ><ExternalLink class="size-4" />
                                        {{ t('audit.preview') }}</a
                                    ></Button
                                >
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-muted-foreground"
                                :disabled="sharingForm.processing"
                                @click="revokeSharing"
                            >
                                <Link2Off class="size-4" /> {{ t('audit.revoke_link') }}
                            </Button>
                        </template>
                        <Button
                            v-else
                            type="button"
                            class="w-full"
                            :disabled="sharingForm.processing"
                            @click="enableSharing"
                        >
                            <LoaderCircle
                                v-if="sharingForm.processing"
                                class="size-4 animate-spin"
                            /><Share2 v-else class="size-4" /> {{ t('audit.enable_public_link') }}
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            {{ t('audit.moving_the_report_to_draft_disables_the_link_do_not_share_it_with_unintended_recipients') }}
                        </p>
                    </CardContent>
                </Card>
                <Card v-if="report.is_generated" class="min-w-0">
                    <CardHeader>
                        <CardTitle class="text-base">{{ t('stage3.actions') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <Button
                            as-child
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-full justify-start"
                        >
                            <a :href="`${baseUrl}/${report.id}/pdf`"
                                ><Download class="size-4" /> {{ t('stage3.download_pdf') }}</a
                            >
                        </Button>
                        <Button
                            v-if="can_edit && !report.published_at"
                            type="button"
                            size="sm"
                            class="w-full justify-start"
                            :disabled="isEditing || publicationForm.processing"
                            @click="changePublication(true)"
                        >
                            <LoaderCircle
                                v-if="publicationForm.processing"
                                class="size-4 animate-spin"
                            /><CheckCircle2 v-else class="size-4" /> {{ t('audit.publish') }}
                        </Button>
                        <Button
                            v-if="can_edit && !!report.published_at"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="w-full justify-start"
                            :disabled="publicationForm.processing"
                            @click="changePublication(false)"
                        >
                            <LoaderCircle
                                v-if="publicationForm.processing"
                                class="size-4 animate-spin"
                            /><RotateCcw v-else class="size-4" /> {{ t('audit.move_to_draft') }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
        <div v-if="can_delete" class="flex justify-end">
            <Button
                variant="outline"
                type="button"
                :disabled="deleteForm.processing"
                @click="removeReport"
            >
                <LoaderCircle
                    v-if="deleteForm.processing"
                    class="size-4 animate-spin"
                /><Trash2 v-else class="size-4" /> {{ t('audit.delete_report') }}
            </Button>
        </div>
    </div>
</template>
