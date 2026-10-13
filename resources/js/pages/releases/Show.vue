<script setup lang="ts">
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
const plannedDateFormatter = new DateFormatter('en-US', {
    dateStyle: 'medium',
});
watch(
    () => props.release.planned_for,
    (value) => {
        plannedDate.value = value ? parseDate(value.slice(0, 10)) : undefined;
    },
);

const draftBody = ref(props.changelog.draft_body);
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
        'Could not complete the action. Review the form and try again.',
    );

const confirmFinalAction = (event: MouseEvent, message: string) => {
    if (!window.confirm(message)) {
        event.preventDefault();
    }
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] },
});
</script>

<template>
    <Head :title="`v${release.version} · ${project.name}`" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Button as-child size="sm" variant="ghost" class="-ml-3 w-fit">
            <Link :href="`/projects/${project.id}/releases`"
                ><ArrowLeft class="size-4" /> All releases</Link
            >
        </Button>
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge
                    :variant="
                        release.status === 'released' ? 'default' : 'outline'
                    "
                    >{{ release.status_label }}</Badge
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
                        ><CalendarDays class="size-4" /> Planned date</CardTitle
                    ></CardHeader
                >
                <CardContent class="font-medium">{{
                    release.planned_for ?? 'Not scheduled'
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"
                    ><CardTitle class="flex items-center gap-2 text-sm"
                        ><Clock3 class="size-4" /> Released at</CardTitle
                    ></CardHeader
                >
                <CardContent class="font-medium">{{
                    release.released_at
                        ? new Date(release.released_at).toLocaleString()
                        : 'Not released'
                }}</CardContent>
            </Card>
        </div>
        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Release progress</CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ release_progress.completed }} of
                        {{ release_progress.total }}
                        {{ release_progress.total === 1 ? 'task' : 'tasks' }}
                        completed
                    </p>
                    <span
                        class="text-2xl font-semibold tracking-tight tabular-nums"
                        >{{ release_progress.percentage }}%</span
                    >
                </div>
                <div
                    role="progressbar"
                    aria-label="Release completion"
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
                        Done</span
                    >
                    <span class="inline-flex items-center gap-2"
                        ><CircleDot
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><span class="font-medium tabular-nums">{{
                            release_progress.in_progress
                        }}</span>
                        In progress</span
                    >
                    <span class="inline-flex items-center gap-2"
                        ><Circle
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><span class="font-medium tabular-nums">{{
                            release_progress.todo
                        }}</span>
                        Todo</span
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
                        Canceled</span
                    >
                </div>
                <p
                    v-if="release_progress.total === 0"
                    class="text-xs text-muted-foreground"
                >
                    Assign tasks to this release to track its progress.
                </p>
                <p
                    v-else-if="
                        release_progress.done > release_progress.completed
                    "
                    class="text-xs text-muted-foreground"
                >
                    Some tasks marked Done do not have a completion date and are
                    not counted as completed.
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
                                Release is not ready to publish
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ remainingTasks }}
                                {{
                                    remainingTasks === 1
                                        ? 'task needs'
                                        : 'tasks need'
                                }}
                                attention. Complete or remove
                                {{ remainingTasks === 1 ? 'it' : 'them' }}
                                before publishing.
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
                                    ? 'Done · Missing completion date'
                                    : task.status
                            }}</span>
                        </li>
                    </ul>
                    <p
                        v-if="remainingTasks > release_blockers.length"
                        class="text-xs text-muted-foreground"
                    >
                        And {{ remainingTasks - release_blockers.length }} more
                        {{
                            remainingTasks - release_blockers.length === 1
                                ? 'task'
                                : 'tasks'
                        }}. See the included tasks below.
                    </p>
                </div>

                <div class="space-y-4 border-t pt-5">
                    <div class="space-y-1.5">
                        <h3 class="text-sm font-semibold">Release lifecycle</h3>
                        <p
                            v-if="release.status === 'released'"
                            class="text-sm text-muted-foreground"
                        >
                            This release has been published and is now locked.
                            Its tasks cannot be reassigned.
                        </p>
                        <p
                            v-else-if="release.status === 'canceled'"
                            class="text-sm text-muted-foreground"
                        >
                            This release was canceled and is now locked.
                        </p>
                        <p
                            v-else-if="!can_manage_release"
                            class="text-sm text-muted-foreground"
                        >
                            Only project managers can change the release status.
                        </p>
                        <template v-else>
                            <p
                                v-if="release.status === 'draft'"
                                class="text-sm text-muted-foreground"
                            >
                                Plan this draft or cancel it. Planning does not
                                publish the release.
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                Publish this planned release or cancel it. Both
                                actions are final.
                            </p>
                            <p
                                v-if="
                                    release.status === 'planned' &&
                                    release_progress.total === 0
                                "
                                class="text-xs text-muted-foreground"
                            >
                                There are no assigned tasks. You can still
                                publish this release.
                            </p>
                            <p
                                v-else-if="
                                    release.status === 'planned' &&
                                    remainingTasks === 0
                                "
                                class="text-xs text-muted-foreground"
                            >
                                All included tasks are completed.
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
                                        >Planned date
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >(optional)</span
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
                                                        : 'Select a date'
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
                                                    Clear date
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
                                                ? 'Planning…'
                                                : 'Mark as planned'
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
                                            'Publish this release? You will not be able to change its status or task assignments.',
                                        )
                                    "
                                    ><LoaderCircle
                                        v-if="processing"
                                        class="size-4 animate-spin"
                                    /><Package v-else class="size-4" />
                                    {{
                                        processing
                                            ? 'Publishing…'
                                            : 'Publish release'
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
                                            'Cancel this release? This cannot be undone.',
                                        )
                                    "
                                    ><LoaderCircle
                                        v-if="processing"
                                        class="size-4 animate-spin"
                                    /><X v-else class="size-4" />
                                    {{
                                        processing
                                            ? 'Canceling…'
                                            : 'Cancel release'
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
                    ><Package class="size-4" /> Release notes</CardTitle
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
                    No internal release notes provided.
                </p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2"
                    ><FileText class="size-4" /> Release changelog</CardTitle
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
                                    ? 'Published'
                                    : 'Not published'
                            }}</Badge
                        >
                        <span
                            v-if="changelog.published_at"
                            class="text-xs text-muted-foreground"
                            >{{
                                new Date(
                                    changelog.published_at,
                                ).toLocaleString()
                            }}</span
                        >
                    </div>
                    <p
                        v-if="changelog.published_locale"
                        class="text-xs text-muted-foreground"
                    >
                        Published language:
                        {{ languageLabel(changelog.published_locale) }}
                    </p>
                    <template v-if="changelog.published_html">
                        <p class="text-sm font-medium">Published content</p>
                        <div
                            class="release-changelog-preview max-w-none rounded-md border p-4 text-sm break-words [&_a]:underline [&_code]:font-mono [&_h1]:text-lg [&_h2]:text-base [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_ul]:list-disc"
                            v-html="changelog.published_html"
                        ></div>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        No published changelog yet.
                    </p>
                </div>
                <template v-if="can_edit_changelog">
                    <div class="space-y-3 border-t pt-5">
                        <h3 class="text-sm font-semibold">
                            Edit draft (Markdown)
                        </h3>
                        <div
                            class="space-y-4 rounded-md border bg-muted/20 p-4"
                        >
                            <div class="space-y-1">
                                <p class="text-sm font-medium">
                                    Generate with AI
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Uses assigned tasks, discussion, and
                                    activity. Sends these to Agent AI; replaces
                                    only the saved draft.
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
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-end"
                                >
                                    <div class="w-full space-y-1.5 sm:w-64">
                                        <label
                                            for="changelog-language"
                                            class="block text-sm font-medium"
                                            >Output language</label
                                        >
                                        <Select v-model="selectedLanguage">
                                            <SelectTrigger
                                                id="changelog-language"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Choose a language"
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
                                                    >Other language…</SelectItem
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
                                            >Language code</label
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
                                                'Generate with AI? Task data will be sent to an external AI provider. The saved draft will be replaced; published content stays unchanged.',
                                            )
                                        "
                                    >
                                        <LoaderCircle
                                            v-if="processing"
                                            class="size-4 animate-spin"
                                        /><FileText v-else class="size-4" />
                                        {{
                                            processing
                                                ? 'Generating…'
                                                : 'Generate draft with AI'
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
                                Configure an AI provider in your local .env to
                                enable generation.
                            </p>
                            <p
                                v-else-if="task_count === 0"
                                class="text-xs text-muted-foreground"
                            >
                                Assign at least one task to this release to
                                enable generation.
                            </p>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Saving a draft never changes published content. Save
                            before previewing.
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
                                placeholder="## Added
- New feature ..."
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
                                        ? 'Saving…'
                                        : 'Save changelog draft'
                                }}</Button
                            >
                        </Form>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold">
                            Saved draft preview
                        </h3>
                        <p
                            v-if="changelog.draft_locale"
                            class="text-xs text-muted-foreground"
                        >
                            Draft language:
                            {{ languageLabel(changelog.draft_locale) }}
                        </p>
                        <div
                            v-if="changelog.draft_body.trim()"
                            class="release-changelog-preview max-w-none rounded-md border bg-muted/20 p-4 text-sm break-words [&_a]:underline [&_code]:font-mono [&_h1]:text-lg [&_h2]:text-base [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_strong]:font-semibold [&_ul]:list-disc"
                            v-html="changelog.draft_html"
                        ></div>
                        <p v-else class="text-sm text-muted-foreground">
                            Save some Markdown to see its preview here.
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
                                        'Publish the saved changelog? This updates the published snapshot.',
                                    )
                                "
                            >
                                <LoaderCircle
                                    v-if="processing"
                                    class="size-4 animate-spin"
                                /><Package v-else class="size-4" />
                                {{
                                    processing
                                        ? 'Publishing…'
                                        : changelog.published_at
                                          ? 'Publish changes'
                                          : 'Publish changelog'
                                }}
                            </Button>
                        </Form>
                        <p class="text-xs text-muted-foreground">
                            Only a saved draft can be published. Later edits
                            remain private until published again.
                        </p>
                    </div>
                    <p v-else class="text-xs text-muted-foreground">
                        Publish the Release before publishing its changelog.
                    </p>
                </template>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>Included tasks ({{ task_count }})</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p
                    v-if="tasks.data.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No tasks assigned to this release yet.
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
                            <span v-if="task.completed">· Completed</span>
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
                            {{ processing ? 'Removing…' : 'Remove' }}</Button
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
                            >Previous</Link
                        ></Button
                    >
                    <span v-else></span>
                    <span class="text-xs text-muted-foreground"
                        >Page {{ tasks.current_page }} /
                        {{ tasks.last_page }}</span
                    >
                    <Button
                        v-if="tasks.next_page_url"
                        as-child
                        size="sm"
                        variant="outline"
                        ><Link :href="tasks.next_page_url" preserve-scroll
                            >Next</Link
                        ></Button
                    >
                </div>
            </CardContent>
        </Card>
        <Card v-if="can_manage_tasks">
            <CardHeader><CardTitle>Add existing task</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="completed_available_count > 0"
                    class="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            {{ completed_available_count }} completed
                            {{
                                completed_available_count === 1
                                    ? 'task'
                                    : 'tasks'
                            }}
                            ready to add
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Only unassigned Done tasks are included. You can
                            remove them individually.
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
                            {{ processing ? 'Adding…' : 'Add all completed' }}
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
                        :placeholder="`Search title or ${project.key}-12`"
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
                        {{ processing ? 'Searching…' : 'Search' }}</Button
                    >
                </Form>
                <p
                    v-if="available_count > 0"
                    class="text-xs text-muted-foreground"
                >
                    {{ available_count }} available tasks<span
                        v-if="available_count > available_tasks.length"
                    >
                        · showing latest {{ available_tasks.length }} — narrow
                        your search</span
                    >
                </p>
                <div
                    v-else
                    class="flex flex-col gap-2 rounded-md border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        {{
                            task_search
                                ? 'No unassigned tasks match your search.'
                                : 'No available tasks to add.'
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
                            >Clear search</Link
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
                                >Task</label
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
                                        placeholder="Select unassigned task"
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
                            {{ processing ? 'Adding…' : 'Add to release' }}
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
