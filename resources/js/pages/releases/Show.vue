<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CalendarDays,
    CheckCircle2,
    Circle,
    CircleDot,
    Clock3,
    FileText,
    LoaderCircle,
    Package,
    Plus,
    X,
} from '@lucide/vue';
import type { DateValue } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, ref, shallowRef, watch } from 'vue';
import { toast } from 'vue-sonner';

type Release = {
    id: number;
    version: string;
    name: string | null;
    description: string | null;
    status: string;
    status_label: string;
    planned_for: string | null;
    released_at: string | null;
    created_at: string | null;
};

type Changelog = {
    draft_body: string;
    draft_locale: string | null;
    published_locale: string | null;
    draft_html: string | null;
    published_html: string | null;
    published_at: string | null;
    has_unpublished_changes: boolean;
};

type ReleaseTask = {
    id: number;
    reference: string;
    title: string;
    status: string;
    completed: boolean;
};

type ReleaseBlocker = {
    id: number;
    reference: string;
    title: string;
    status: string;
    missing_completion_date: boolean;
};

type ReleaseProgress = {
    total: number;
    completed: number;
    todo: number;
    in_progress: number;
    done: number;
    canceled: number;
    percentage: number;
};

const props = defineProps<{
    project: { id: number; name: string; key: string };
    release: Release;
    can_manage_release: boolean;
    can_manage_tasks: boolean;
    can_edit_changelog: boolean;
    ai_configured: boolean;
    default_ai_language: string;
    changelog: Changelog;
    tasks: {
        data: ReleaseTask[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    task_count: number;
    release_progress: ReleaseProgress;
    release_blockers: ReleaseBlocker[];
    available_tasks: ReleaseTask[];
    available_count: number;
    completed_available_count: number;
    task_search: string;
}>();

// The date picker holds a calendar day without a time zone. The hidden
// form field still submits YYYY-MM-DD, as required by the Laravel request.
const plannedDate = shallowRef<DateValue | undefined>(
    props.release.planned_for
        ? parseDate(props.release.planned_for.slice(0, 10))
        : undefined,
);
const plannedDateFormatter = computed(() => new DateFormatter(locale.value, {
    dateStyle: 'medium',
}));
watch(
    () => props.release.planned_for,
    (value) => {
        plannedDate.value = value ? parseDate(value.slice(0, 10)) : undefined;
    },
);

const draftBody = ref(props.changelog.draft_body);
const includeVcsInChangelog = ref(false);
const hasUnsavedDraftChanges = computed(
    () => draftBody.value !== props.changelog.draft_body,
);
const selectedTaskId = ref('');
watch(
    () => props.available_tasks,
    (tasks) => {
        if (!tasks.some((task) => String(task.id) === selectedTaskId.value)) {
            selectedTaskId.value = '';
        }
    },
);
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
    { value: 'tr', label: 'Türkçe' },
    { value: 'ja', label: '日本語' },
    { value: 'ko', label: '한국어' },
    { value: 'zh-Hans', label: '简体中文' },
    { value: 'zh-Hant', label: '繁體中文' },
    { value: 'ar', label: 'العربية' },
] as const;

const isPresetLanguage = (code: string) =>
    languageOptions.some((option) => option.value === code);
const selectedLanguage = ref(
    isPresetLanguage(props.default_ai_language)
        ? props.default_ai_language
        : '__other',
);
const customLanguage = ref(
    isPresetLanguage(props.default_ai_language)
        ? ''
        : props.default_ai_language,
);
const outputLanguage = computed(() =>
    selectedLanguage.value === '__other'
        ? customLanguage.value.trim()
        : selectedLanguage.value,
);
const languageLabel = (code: string | null) => {
    if (!code) return null;
    try {
        return (
            new Intl.DisplayNames([props.default_ai_language], {
                type: 'language',
            }).of(code) ?? code
        );
    } catch {
        return code;
    }
};
watch(
    () => props.changelog.draft_body,
    (body) => {
        draftBody.value = body;
    },
);

const remainingTasks = computed(() =>
    Math.max(
        0,
        props.release_progress.total - props.release_progress.completed,
    ),
);

const releaseUrl = `/projects/${props.project.id}/releases/${props.release.id}`;

// Inertia <Form> already sends asynchronous requests. Preserve the scroll position
// when the server redirects back with updated props and a Sonner flash message.
const stayOnRelease = { preserveScroll: true, preserveState: true };
const stayOnSearch = {
    preserveScroll: true,
    preserveState: true,
    replace: true,
};
const notifyFormError = () =>
    toast.error(
        t('audit.could_not_complete_the_action_review_the_form_and_try_again'),
    );

const confirmFinalAction = (event: MouseEvent, message: string) => {
    if (!window.confirm(message)) {
        event.preventDefault();
    }
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] },
});
const { t, locale, formatText, translateLiteral } = useI18n();
</script>

<template>
    <Head :title="`v${release.version} · ${project.name}`" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Button as-child size="sm" variant="ghost" class="-ml-3 w-fit">
            <Link :href="`/projects/${project.id}/releases`"
                ><ArrowLeft class="size-4" /> {{ t('stage3.all_releases') }}</Link
            >
        </Button>
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge
                    :variant="
                        release.status === 'released' ? 'default' : 'outline'
                    "
                    >{{ translateLiteral(release.status_label) }}</Badge
                >
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    v{{ release.version }}
                </h1>
                <p
                    v-if="release.name"
                    class="mt-1 text-base text-muted-foreground"
                >
                    {{ release.name }}
                </p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <Card>
                <CardHeader class="pb-2"
                    ><CardTitle class="flex items-center gap-2 text-sm"
                        ><CalendarDays class="size-4" /> {{ t('stage3.planned_date') }}</CardTitle
                    ></CardHeader
                >
                <CardContent class="font-medium">{{
                    release.planned_for ?? t('audit.not_scheduled')
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"
                    ><CardTitle class="flex items-center gap-2 text-sm"
                        ><Clock3 class="size-4" /> {{ t('stage3.released_at') }}</CardTitle
                    ></CardHeader
                >
                <CardContent class="font-medium">{{
                    release.released_at
                        ? new Date(release.released_at).toLocaleString(locale)
                        : t('audit.not_released')
                }}</CardContent>
            </Card>
        </div>
        <Card>
            <CardHeader class="pb-3">
                <CardTitle>{{ t('stage3.release_progress') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ formatText('audit.count_completed', { completed: release_progress.completed, total: release_progress.total }) }}
                    </p>
                    <span
                        class="text-2xl font-semibold tracking-tight tabular-nums"
                        >{{ release_progress.percentage }}%</span
                    >
                </div>
                <div
                    role="progressbar"
                    :aria-label="t('audit.release_progress_label')"
                    :aria-valuenow="release_progress.percentage"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    class="h-2 w-full overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full rounded-full bg-primary transition-[width] duration-300"
                        :style="{ width: `${release_progress.percentage}%` }"
                    ></div>
                </div>
                <div
                    class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm"
                >
                    <span class="inline-flex items-center gap-2"
                        ><CheckCircle2
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><span class="font-medium tabular-nums">{{
                            release_progress.done
                        }}</span>
                        {{ t('audit.done') }}</span
                    >
                    <span class="inline-flex items-center gap-2"
                        ><CircleDot
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><span class="font-medium tabular-nums">{{
                            release_progress.in_progress
                        }}</span>
                        {{ t('audit.in_progress') }}</span
                    >
                    <span class="inline-flex items-center gap-2"
                        ><Circle
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><span class="font-medium tabular-nums">{{
                            release_progress.todo
                        }}</span>
                        {{ t('audit.todo') }}</span
                    >
                    <span
                        v-if="release_progress.canceled > 0"
                        class="inline-flex items-center gap-2"
                        ><Ban
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><span class="font-medium tabular-nums">{{
                            release_progress.canceled
                        }}</span>
                        {{ t('audit.canceled') }}</span
                    >
                </div>
                <p
                    v-if="release_progress.total === 0"
                    class="text-xs text-muted-foreground"
                >
                    {{ t('audit.assign_tasks_to_this_release_to_track_its_progress') }}
                </p>
                <p
                    v-else-if="
                        release_progress.done > release_progress.completed
                    "
                    class="text-xs text-muted-foreground"
                >
                    {{ t('audit.some_tasks_marked_done_do_not_have_a_completion_date_and_are_not_counted_as_completed') }}
                </p>

                <div
                    v-if="release.status === 'planned' && remainingTasks > 0"
                    class="space-y-3 rounded-md border border-border bg-muted/30 p-3 sm:p-4"
                    role="status"
                >
                    <div class="flex items-start gap-2.5">
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-semibold">
                                {{ t('audit.release_is_not_ready_to_publish') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatText('audit.issues_remaining', { count: remainingTasks }) }}
                            </p>
                        </div>
                    </div>
                    <ul
                        v-if="release_blockers.length"
                        class="divide-y divide-border rounded-md border bg-background px-3"
                    >
                        <li
                            v-for="task in release_blockers"
                            :key="task.id"
                            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 text-sm"
                        >
                            <Link
                                :href="`/projects/${project.id}/tasks/${task.id}`"
                                class="min-w-0 font-medium break-words hover:underline"
                            >
                                {{ task.reference }} · {{ task.title }}
                            </Link>
                            <span class="text-xs text-muted-foreground">{{
                                task.missing_completion_date
                                    ? t('audit.release_need_completion_date')
                                    : task.status
                            }}</span>
                        </li>
                    </ul>
                    <p
                        v-if="remainingTasks > release_blockers.length"
                        class="text-xs text-muted-foreground"
                    >
                        {{ formatText('audit.more_remaining', { count: remainingTasks - release_blockers.length }) }}
                    </p>
                </div>

                <div class="space-y-4 border-t pt-5">
                    <div class="space-y-1.5">
                        <h3 class="text-sm font-semibold">{{ t('stage3.release_lifecycle') }}</h3>
                        <p
                            v-if="release.status === 'released'"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('audit.this_release_has_been_published_and_is_now_locked_its_tasks_cannot_be_reassigned') }}
                        </p>
                        <p
                            v-else-if="release.status === 'canceled'"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('audit.this_release_was_canceled_and_is_now_locked') }}
                        </p>
                        <p
                            v-else-if="!can_manage_release"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('audit.only_project_managers_can_change_the_release_status') }}
                        </p>
                        <template v-else>
                            <p
                                v-if="release.status === 'draft'"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('audit.plan_this_draft_or_cancel_it_planning_does_not_publish_the_release') }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ t('audit.publish_this_planned_release_or_cancel_it_both_actions_are_final') }}
                            </p>
                            <p
                                v-if="
                                    release.status === 'planned' &&
                                    release_progress.total === 0
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{ t('audit.there_are_no_assigned_tasks_you_can_still_publish_this_release') }}
                            </p>
                            <p
                                v-else-if="
                                    release.status === 'planned' &&
                                    remainingTasks === 0
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{ t('audit.all_included_tasks_are_completed') }}
                            </p>
                        </template>
                    </div>
                    <template
                        v-if="
                            can_manage_release &&
                            (release.status === 'draft' ||
                                release.status === 'planned')
                        "
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <Form
                                v-if="release.status === 'draft'"
                                :action="`${releaseUrl}/status`"
                                method="patch"
                                :options="stayOnRelease"
                                @error="notifyFormError"
                                class="flex flex-col gap-3 sm:flex-row sm:items-end"
                                v-slot="{ errors, processing }"
                            >
                                <input type="hidden" name="status" value="2" />
                                <div class="w-full space-y-1.5 sm:w-64">
                                    <label
                                        for="release-planned-date"
                                        class="block text-sm font-medium"
                                        >{{ t('audit.planned_date') }}
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >{{ t('stage3.optional') }}</span
                                        ></label
                                    >
                                    <input
                                        type="hidden"
                                        name="planned_for"
                                        :value="plannedDate?.toString() ?? ''"
                                    />
                                    <Popover v-slot="{ close }">
                                        <PopoverTrigger as-child>
                                            <Button
                                                id="release-planned-date"
                                                type="button"
                                                variant="outline"
                                                class="w-full justify-between text-left font-normal"
                                                :class="
                                                    !plannedDate &&
                                                    'text-muted-foreground'
                                                "
                                            >
                                                <span>{{
                                                    plannedDate
                                                        ? plannedDateFormatter.format(
                                                              plannedDate.toDate(
                                                                  getLocalTimeZone(),
                                                              ),
                                                          )
                                                        : t('audit.no_date')
                                                }}</span>
                                                <CalendarDays
                                                    class="size-4 shrink-0 opacity-70"
                                                />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            align="start"
                                            class="w-auto p-0"
                                        >
                                            <Calendar
                                                v-model="plannedDate"
                                                :default-placeholder="
                                                    today(getLocalTimeZone())
                                                "
                                                layout="month-and-year"
                                                initial-focus
                                                @update:model-value="close"
                                            />
                                            <div class="border-t p-2">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="w-full"
                                                    :disabled="!plannedDate"
                                                    @click="
                                                        plannedDate = undefined;
                                                        close();
                                                    "
                                                >
                                                    {{ t('audit.clear_date') }}
                                                </Button>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                    <InputError :message="errors.planned_for" />
                                </div>
                                <div class="space-y-1.5">
                                    <Button
                                        type="submit"
                                        class="w-full sm:w-auto"
                                        :disabled="processing"
                                        ><LoaderCircle
                                            v-if="processing"
                                            class="size-4 animate-spin"
                                        /><CalendarDays v-else class="size-4" />
                                        {{
                                            processing
                                                ? t('audit.planning')
                                                : t('audit.mark_as_planned')
                                        }}</Button
                                    >
                                    <InputError :message="errors.status" />
                                </div>
                            </Form>
                            <Form
                                v-if="release.status === 'planned'"
                                :action="`${releaseUrl}/status`"
                                method="patch"
                                :options="stayOnRelease"
                                @error="notifyFormError"
                                class="space-y-1.5"
                                v-slot="{ errors, processing }"
                            >
                                <input type="hidden" name="status" value="3" />
                                <Button
                                    type="submit"
                                    class="w-full sm:w-auto"
                                    :disabled="processing || remainingTasks > 0"
                                    @click="
                                        confirmFinalAction(
                                            $event,
                                            t('audit.publish_this_release_you_will_not_be_able_to_change_its_status_or_task_assignments'),
                                        )
                                    "
                                    ><LoaderCircle
                                        v-if="processing"
                                        class="size-4 animate-spin"
                                    /><Package v-else class="size-4" />
                                    {{
                                        processing
                                            ? t('audit.publishing')
                                            : t('audit.publish_release')
                                    }}</Button
                                >
                                <InputError :message="errors.status" />
                            </Form>
                            <Form
                                :action="`${releaseUrl}/status`"
                                method="patch"
                                :options="stayOnRelease"
                                @error="notifyFormError"
                                class="space-y-1.5"
                                v-slot="{ errors, processing }"
                            >
                                <input type="hidden" name="status" value="4" />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    class="w-full sm:w-auto"
                                    :disabled="processing"
                                    @click="
                                        confirmFinalAction(
                                            $event,
                                            t('audit.cancel_this_release_this_cannot_be_undone'),
                                        )
                                    "
                                    ><LoaderCircle
                                        v-if="processing"
                                        class="size-4 animate-spin"
                                    /><X v-else class="size-4" />
                                    {{
                                        processing
                                            ? t('audit.canceling')
                                            : t('audit.cancel_release')
                                    }}</Button
                                >
                                <InputError :message="errors.status" />
                            </Form>
                        </div>
                    </template>
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader
                ><CardTitle class="flex items-center gap-2"
                    ><Package class="size-4" /> {{ t('stage3.release_notes') }}</CardTitle
                ></CardHeader
            >
            <CardContent>
                <p
                    v-if="release.description"
                    class="text-sm leading-relaxed whitespace-pre-wrap"
                >
                    {{ release.description }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('audit.no_internal_release_notes_provided') }}
                </p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2"
                    ><FileText class="size-4" /> {{ t('stage3.release_changelog') }}</CardTitle
                >
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            :variant="
                                changelog.published_at ? 'secondary' : 'outline'
                            "
                            >{{
                                changelog.published_at
                                    ? t('audit.published')
                                    : t('fix.not_published')
                            }}</Badge
                        >
                        <span
                            v-if="changelog.published_at"
                            class="text-xs text-muted-foreground"
                            >{{
                                new Date(
                                    changelog.published_at,
                                ).toLocaleString(locale)
                            }}</span
                        >
                    </div>
                    <p
                        v-if="changelog.published_locale"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('fix.release_published_language') }}
                        {{ languageLabel(changelog.published_locale) }}
                    </p>
                    <template v-if="changelog.published_html">
                        <p class="text-sm font-medium">{{ t('stage3.published_content') }}</p>
                        <div
                            class="release-changelog-preview max-w-none rounded-md border p-4 text-sm break-words [&_a]:underline [&_code]:font-mono [&_h1]:text-lg [&_h2]:text-base [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_ul]:list-disc"
                            v-html="changelog.published_html"
                        ></div>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ t('audit.no_published_changelog_yet') }}
                    </p>
                </div>
                <template v-if="can_edit_changelog">
                    <div class="space-y-3 border-t pt-5">
                        <h3 class="text-sm font-semibold">
                            {{ t('audit.edit_draft_markdown') }}
                        </h3>
                        <div
                            class="space-y-4 rounded-md border bg-muted/20 p-4"
                        >
                            <div class="space-y-1">
                                <p class="text-sm font-medium">
                                    {{ t('audit.generate_with_ai') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('audit.uses_assigned_tasks_discussion_and_activity_sends_these_to_agent_ai_replaces_only_the_save') }}
                                </p>
                            </div>
                            <Form
                                :action="`${releaseUrl}/changelog/generate`"
                                method="post"
                                :options="stayOnRelease"
                                @error="notifyFormError"
                                v-slot="{ errors, processing }"
                                class="space-y-2"
                            >
                                <input
                                    type="hidden"
                                    name="language"
                                    :value="outputLanguage"
                                />
                                <input type="hidden" name="include_vcs" :value="includeVcsInChangelog ? '1' : '0'" />
                                <label for="changelog-include-vcs" class="flex items-start gap-2 text-sm">
                                    <Checkbox id="changelog-include-vcs" :model-value="includeVcsInChangelog" class="mt-0.5" @update:model-value="(checked) => includeVcsInChangelog = checked === true" />
                                    <span>
                                        {{ t('vcs.includeInChangelog') }}
                                        <span class="block text-xs text-muted-foreground">{{ t('vcs.changelogHelp') }}</span>
                                        <span class="mt-1 block text-xs text-muted-foreground">{{ t('vcs.changelogPrivacy') }}</span>
                                    </span>
                                </label>
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-end"
                                >
                                    <div class="w-full space-y-1.5 sm:w-64">
                                        <label
                                            for="changelog-language"
                                            class="block text-sm font-medium"
                                            >{{ t('stage3.output_language') }}</label
                                        >
                                        <Select v-model="selectedLanguage">
                                            <SelectTrigger
                                                id="changelog-language"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    :placeholder="t('audit.choose_a_language')"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="language in languageOptions"
                                                    :key="language.value"
                                                    :value="language.value"
                                                >
                                                    {{ language.label }}
                                                </SelectItem>
                                                <SelectItem value="__other"
                                                    >{{ t('stage3.other_language') }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div
                                        v-if="selectedLanguage === '__other'"
                                        class="w-full space-y-1.5 sm:w-48"
                                    >
                                        <label
                                            for="changelog-custom-language"
                                            class="block text-sm font-medium"
                                            >{{ t('stage3.language_code') }}</label
                                        >
                                        <Input
                                            id="changelog-custom-language"
                                            v-model="customLanguage"
                                            type="text"
                                            required
                                            maxlength="35"
                                            pattern="[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*"
                                            autocomplete="off"
                                            placeholder="e.g. sv, hi, zh-Hant-TW"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        class="w-full sm:w-auto"
                                        :disabled="
                                            processing ||
                                            !ai_configured ||
                                            task_count === 0 ||
                                            !outputLanguage
                                        "
                                        @click="
                                            confirmFinalAction(
                                                $event,
                                                t('audit.generate_with_ai_task_data_will_be_sent_to_an_external_ai_provider_the_saved_draft_will_be'),
                                            )
                                        "
                                    >
                                        <LoaderCircle
                                            v-if="processing"
                                            class="size-4 animate-spin"
                                        /><FileText v-else class="size-4" />
                                        {{
                                            processing
                                                ? t('audit.generating')
                                                : t('audit.generate_draft_with_ai')
                                        }}
                                    </Button>
                                </div>
                                <InputError :message="errors.language" />
                                <InputError :message="errors.ai_generation" />
                            </Form>
                            <p
                                v-if="!ai_configured"
                                class="text-xs text-muted-foreground"
                            >
                                {{ t('audit.configure_an_ai_provider_in_your_local_env_to_enable_generation') }}
                            </p>
                            <p
                                v-else-if="task_count === 0"
                                class="text-xs text-muted-foreground"
                            >
                                {{ t('audit.assign_at_least_one_task_to_this_release_to_enable_generation') }}
                            </p>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('audit.saving_a_draft_never_changes_published_content_save_before_previewing') }}
                        </p>
                        <Form
                            :action="`${releaseUrl}/changelog`"
                            method="put"
                            :options="stayOnRelease"
                            @error="notifyFormError"
                            v-slot="{ errors, processing }"
                            class="space-y-3"
                        >
                            <textarea
                                v-model="draftBody"
                                name="draft_body"
                                rows="9"
                                maxlength="50000"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :placeholder="t('releases.changelogPlaceholder')"
                            ></textarea>
                            <InputError :message="errors.draft_body" />
                            <Button
                                type="submit"
                                :disabled="
                                    processing || !hasUnsavedDraftChanges
                                "
                                ><LoaderCircle
                                    v-if="processing"
                                    class="size-4 animate-spin"
                                />
                                {{
                                    processing
                                        ? t('audit.saving')
                                        : t('audit.save_changelog_draft')
                                }}</Button
                            >
                        </Form>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold">
                            {{ t('audit.saved_draft_preview') }}
                        </h3>
                        <p
                            v-if="changelog.draft_locale"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('fix.release_draft_language') }}
                            {{ languageLabel(changelog.draft_locale) }}
                        </p>
                        <div
                            v-if="changelog.draft_body.trim()"
                            class="release-changelog-preview max-w-none rounded-md border bg-muted/20 p-4 text-sm break-words [&_a]:underline [&_code]:font-mono [&_h1]:text-lg [&_h2]:text-base [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_ul]:list-disc"
                            v-html="changelog.draft_html"
                        ></div>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('audit.save_some_markdown_to_see_its_preview_here') }}
                        </p>
                    </div>
                    <div
                        v-if="release.status === 'released'"
                        class="space-y-2 border-t pt-4"
                    >
                        <Form
                            :action="`${releaseUrl}/changelog/publish`"
                            method="post"
                            :options="stayOnRelease"
                            @error="notifyFormError"
                            v-slot="{ errors, processing }"
                        >
                            <InputError
                                :message="errors.changelog"
                                class="mb-2"
                            />
                            <Button
                                type="submit"
                                :disabled="
                                    processing ||
                                    !changelog.has_unpublished_changes
                                "
                                @click="
                                    confirmFinalAction(
                                        $event,
                                        t('audit.publish_the_saved_changelog_this_updates_the_published_snapshot'),
                                    )
                                "
                            >
                                <LoaderCircle
                                    v-if="processing"
                                    class="size-4 animate-spin"
                                /><Package v-else class="size-4" />
                                {{
                                    processing
                                        ? t('audit.publishing')
                                        : changelog.published_at
                                          ? t('audit.publish_changes')
                                          : t('audit.publish_changelog')
                                }}
                            </Button>
                        </Form>
                        <p class="text-xs text-muted-foreground">
                            {{ t('audit.only_a_saved_draft_can_be_published_later_edits_remain_private_until_published_again') }}
                        </p>
                    </div>
                    <p v-else class="text-xs text-muted-foreground">
                        {{ t('audit.publish_the_release_before_publishing_its_changelog') }}
                    </p>
                </template>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>{{ formatText('fix.included_tasks', { count: task_count }) }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p
                    v-if="tasks.data.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('audit.no_tasks_assigned_to_this_release_yet') }}
                </p>
                <div
                    v-for="task in tasks.data"
                    :key="task.id"
                    class="flex items-center justify-between gap-3 border-b pb-3 last:border-0"
                >
                    <div class="min-w-0 space-y-1">
                        <Link
                            :href="`/projects/${project.id}/tasks/${task.id}`"
                            class="text-sm font-medium hover:underline"
                            >{{ task.reference }} · {{ task.title }}</Link
                        >
                        <p class="text-xs text-muted-foreground">
                            {{ task.status }}
                            <span v-if="task.completed">{{ t('stage3.completed') }}</span>
                        </p>
                    </div>
                    <Form
                        v-if="can_manage_tasks"
                        :action="`${releaseUrl}/tasks/${task.id}`"
                        method="delete"
                        :options="stayOnRelease"
                        @error="notifyFormError"
                        v-slot="{ processing, errors }"
                        class="flex flex-col items-end gap-1"
                    >
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            :disabled="processing"
                            ><LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            /><X v-else class="size-4" />
                            {{ processing ? t('audit.removing') : t('audit.remove') }}</Button
                        >
                        <InputError :message="errors.release" />
                    </Form>
                </div>
                <div
                    v-if="tasks.last_page > 1"
                    class="flex items-center justify-between gap-2"
                >
                    <Button
                        v-if="tasks.prev_page_url"
                        as-child
                        size="sm"
                        variant="outline"
                        ><Link :href="tasks.prev_page_url" preserve-scroll
                            >{{ t('stage3.previous') }}</Link
                        ></Button
                    >
                    <span v-else></span>
                    <span class="text-xs text-muted-foreground"
                        >{{ formatText('audit.count_pages', { page: tasks.current_page, pages: tasks.last_page }) }}</span
                    >
                    <Button
                        v-if="tasks.next_page_url"
                        as-child
                        size="sm"
                        variant="outline"
                        ><Link :href="tasks.next_page_url" preserve-scroll
                            >{{ t('stage3.next') }}</Link
                        ></Button
                    >
                </div>
            </CardContent>
        </Card>
        <Card v-if="can_manage_tasks">
            <CardHeader><CardTitle>{{ t('stage3.add_existing_task') }}</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="completed_available_count > 0"
                    class="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            {{ formatText('audit.available_completed', { count: completed_available_count }) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('audit.only_unassigned_done_tasks_are_included_you_can_remove_them_individually') }}
                        </p>
                    </div>
                    <Form
                        :action="`${releaseUrl}/tasks/completed`"
                        method="post"
                        :options="stayOnRelease"
                        @error="notifyFormError"
                        v-slot="{ errors, processing }"
                        class="shrink-0 space-y-1"
                    >
                        <Button
                            type="submit"
                            :disabled="processing"
                            class="w-full sm:w-auto"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            /><Plus v-else class="size-4" />
                            {{ processing ? t('audit.adding') : t('audit.add_all_completed') }}
                        </Button>
                        <InputError :message="errors.release" />
                    </Form>
                </div>
                <Form
                    :action="releaseUrl"
                    method="get"
                    :options="stayOnSearch"
                    class="flex flex-wrap gap-2"
                    v-slot="{ processing }"
                >
                    <Input
                        name="q"
                        type="search"
                        :default-value="task_search"
                        :placeholder="formatText('audit.search_title', { reference: `${project.key}-12` })"
                        class="min-w-48 flex-1"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                        ><LoaderCircle
                            v-if="processing"
                            class="size-4 animate-spin"
                        />
                        {{ processing ? t('audit.searching') : t('audit.search') }}</Button
                    >
                </Form>
                <p
                    v-if="available_count > 0"
                    class="text-xs text-muted-foreground"
                >
                    {{ formatText('audit.available_tasks', { count: available_count }) }}<span
                        v-if="available_count > available_tasks.length"
                    >
                        · {{ formatText('audit.showing_latest', { count: available_tasks.length }) }}</span
                    >
                </p>
                <div
                    v-else
                    class="flex flex-col gap-2 rounded-md border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        {{
                            task_search
                                ? t('audit.no_unassigned_tasks_match_your_search')
                                : t('audit.no_available_tasks_to_add')
                        }}
                    </p>
                    <Button
                        v-if="task_search"
                        as-child
                        size="sm"
                        variant="outline"
                        class="w-fit"
                    >
                        <Link :href="releaseUrl" preserve-scroll
                            >{{ t('stage3.clear_search') }}</Link
                        >
                    </Button>
                </div>
                <Form
                    v-if="available_tasks.length > 0"
                    :action="`${releaseUrl}/tasks`"
                    method="post"
                    :options="stayOnRelease"
                    @error="notifyFormError"
                    @success="selectedTaskId = ''"
                    v-slot="{ errors, processing }"
                    class="space-y-2"
                >
                    <input
                        type="hidden"
                        name="task_id"
                        :value="selectedTaskId"
                    />
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-full min-w-0 flex-1 space-y-1.5">
                            <label
                                for="release-task-select"
                                class="block text-sm font-medium"
                                >{{ t('stage3.task') }}</label
                            >
                            <Select
                                v-model="selectedTaskId"
                                :disabled="available_tasks.length === 0"
                            >
                                <SelectTrigger
                                    id="release-task-select"
                                    class="w-full min-w-0"
                                >
                                    <SelectValue
                                        :placeholder="t('audit.select_unassigned_task')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="task in available_tasks"
                                        :key="task.id"
                                        :value="String(task.id)"
                                    >
                                        {{ task.reference }} · {{ task.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            type="submit"
                            class="w-full sm:w-auto"
                            :disabled="
                                processing ||
                                !selectedTaskId ||
                                available_tasks.length === 0
                            "
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            /><Plus v-else class="size-4" />
                            {{ processing ? t('audit.adding') : t('audit.add_to_release') }}
                        </Button>
                    </div>
                    <InputError :message="errors.task_id" />
                </Form>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* v-html content needs :deep() because Vue does not scope generated Markdown nodes. */
.release-changelog-preview :deep(h2) {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.release-changelog-preview :deep(h2:first-child) {
    margin-top: 0;
}

.release-changelog-preview :deep(li + li) {
    margin-top: 0.25rem;
}
</style>
