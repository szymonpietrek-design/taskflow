<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    FolderKanban,
    LoaderCircle,
    LockKeyhole,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import WorklogDatePicker from '@/components/worklogs/WorklogDatePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type WorklogEntry = {
    id: number;
    work_date: string;
    duration_minutes: number;
    description: string | null;
    is_creative: boolean;
    can_manage: boolean;
    task: { id: number; title: string; reference: string };
    project: { id: number; name: string };
};
type ProjectOption = {
    id: number;
    name: string;
    key: string;
    workspace_name: string;
};
type TaskOption = { id: number; label: string };

const props = defineProps<{
    date: string;
    timezone: string;
    week: { date: string; total_minutes: number }[];
    daily_limit_minutes: number;
    total_minutes: number;
    visible_minutes: number;
    worklog_projects: ProjectOption[];
    entries: {
        data: WorklogEntry[];
        total: number;
        current_page: number;
        last_page: number;
        previous_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const timezone = getLocalTimeZone();
const localToday = today(timezone).toString();
const dayBefore = computed(() =>
    parseDate(props.date).subtract({ days: 1 }).toString(),
);
const dayAfter = computed(() =>
    parseDate(props.date).add({ days: 1 }).toString(),
);
const previousWeek = computed(() =>
    parseDate(props.week[0].date).subtract({ days: 7 }).toString(),
);
const nextWeek = computed(() =>
    parseDate(props.week[0].date).add({ days: 7 }).toString(),
);
const canGoForward = computed(() => props.date < localToday);
const canGoNextWeek = computed(() => nextWeek.value <= localToday);
const remaining = computed(() =>
    Math.max(0, props.daily_limit_minutes - props.total_minutes),
);
const completion = computed(() =>
    Math.min(100, (props.total_minutes / props.daily_limit_minutes) * 100),
);
const hiddenMinutes = computed(() =>
    Math.max(0, props.total_minutes - props.visible_minutes),
);
const dayLabel = computed(() => formatDay(props.date, { dateStyle: 'long' }));
const weekLabel = computed(
    () =>
        `${formatDay(props.week[0].date, { month: 'short', day: 'numeric' })} – ${formatDay(props.week[6].date, { month: 'short', day: 'numeric', year: 'numeric' })}`,
);

function duration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return (
        [hours ? `${hours}h` : '', rest ? `${rest}m` : '']
            .filter(Boolean)
            .join(' ') || '0m'
    );
}
function formatDay(date: string, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat(locale.value, {
        ...options,
        timeZone: 'UTC',
    }).format(new Date(`${date}T12:00:00Z`));
}
function dayUrl(date: string): string {
    return `/worklogs?${new URLSearchParams({ date, timezone })}`;
}
function changeDate(date: string): void {
    if (date && date <= localToday && date !== props.date)
        router.get(dayUrl(date));
}

// Newly opened global links use the browser's day, including around UTC midnight.
// Preserve an explicitly selected date when moving to the browser timezone.
onMounted(() => {
    if (props.timezone !== timezone) {
        const url = new URL(window.location.href);
        const query = new URLSearchParams({ timezone });
        if (url.searchParams.has('date')) query.set('date', props.date);
        router.get(
            `/worklogs?${query}`,
            {},
            { replace: true, preserveScroll: true },
        );
    }
});

const editorOpen = ref(false);
const editingEntry = ref<WorklogEntry | null>(null);
const deletingEntry = ref<WorklogEntry | null>(null);
const projectId = ref('');
const taskId = ref('');
const taskSearch = ref('');
const taskOptions = ref<TaskOption[]>([]);
const taskLoading = ref(false);
const taskError = ref('');
const form = useForm({
    task_id: 0,
    work_date: props.date,
    timezone,
    hours: 0,
    minutes: 0,
    description: '',
    is_creative: false,
});
const deleteForm = useForm({ timezone });
const editing = computed(() => editingEntry.value !== null);

function closeEditor(): void {
    if (form.processing) return;
    editorOpen.value = false;
    editingEntry.value = null;
    form.reset();
    form.clearErrors();
}
function addEntry(): void {
    editingEntry.value = null;
    form.reset();
    form.clearErrors();
    form.work_date = props.date;
    projectId.value = props.worklog_projects.length
        ? String(props.worklog_projects[0].id)
        : '';
    taskId.value = '';
    taskSearch.value = '';
    editorOpen.value = true;
}
function editEntry(entry: WorklogEntry): void {
    editingEntry.value = entry;
    form.reset();
    form.clearErrors();
    form.work_date = entry.work_date;
    form.hours = Math.floor(entry.duration_minutes / 60);
    form.minutes = entry.duration_minutes % 60;
    form.description = entry.description ?? '';
    form.is_creative = entry.is_creative;
    editorOpen.value = true;
}
function saveEntry(): void {
    if (form.processing) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editorOpen.value = false;
            editingEntry.value = null;
            form.reset();
        },
        onError: () =>
            toast.error(
                t('audit.could_not_save_worklog_check_the_form_and_try_again'),
            ),
    };
    if (editingEntry.value) {
        // The task assignment is immutable when editing a worklog.
        form.transform(({ task_id: _unused, ...values }) => values).patch(
            `/worklogs/${editingEntry.value.id}`,
            options,
        );
    } else {
        form.task_id = Number(taskId.value);
        form.transform((data) => data).post('/worklogs', options);
    }
}
function removeEntry(): void {
    if (!deletingEntry.value || deleteForm.processing) return;
    deleteForm.delete(`/worklogs/${deletingEntry.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deletingEntry.value = null;
        },
        onError: () => toast.error(t('audit.could_not_delete_worklog_entry')),
    });
}

// Task choices are fetched only for the selected project and search string.
// Aborting older requests prevents results for a different project replacing the list.
watch(
    [editorOpen, editing, projectId, taskSearch],
    ([open, isEditing, project, search], _previous, onCleanup) => {
        taskOptions.value = [];
        taskId.value = '';
        taskError.value = '';
        if (!open || isEditing || !project) {
            taskLoading.value = false;
            return;
        }
        const abort = new AbortController();
        const timer = setTimeout(async () => {
            taskLoading.value = true;
            try {
                const response = await fetch(
                    `/worklogs/projects/${encodeURIComponent(project)}/tasks?${new URLSearchParams({ search })}`,
                    {
                        signal: abort.signal,
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    },
                );
                if (!response.ok) throw new Error('Unable to load tasks');
                const data: { tasks: TaskOption[] } = await response.json();
                if (!abort.signal.aborted) taskOptions.value = data.tasks;
            } catch {
                if (!abort.signal.aborted)
                    taskError.value = t('audit.could_not_load_tasks_try_again');
            } finally {
                if (!abort.signal.aborted) taskLoading.value = false;
            }
        }, 180);
        onCleanup(() => {
            clearTimeout(timer);
            abort.abort();
        });
    },
    { immediate: true },
);

defineOptions({
    layout: { breadcrumbs: [{ title: 'My Worklog', href: '/worklogs' }] },
});
const { t, locale, formatText, countLabel } = useI18n();
</script>

<template>
    <Head :title="t('stage2.my_worklog')" />
    <div
        class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6 lg:p-8"
    >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ t('stage2.my_worklog') }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('stage2.track_daily_work_across_all_your_projects_and_workspaces') }}
                </p>
            </div>
            <Button
                v-if="worklog_projects.length"
                type="button"
                @click="addEntry"
                ><Plus class="size-4" /> {{ t('stage2.add_worklog') }}</Button
            >
        </header>

        <section class="space-y-3" :aria-label="t('stage2.choose_work_date')">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        as-child
                        :aria-label="t('stage2.previous_day')"
                        ><Link :href="dayUrl(dayBefore)"
                            ><ChevronLeft class="size-4" /></Link
                    ></Button>
                    <span
                        class="min-w-32 text-center text-sm font-semibold sm:min-w-52"
                        >{{ dayLabel }}</span
                    >
                    <Button
                        v-if="canGoForward"
                        variant="outline"
                        size="icon"
                        as-child
                        :aria-label="t('stage2.next_day')"
                        ><Link :href="dayUrl(dayAfter)"
                            ><ChevronRight class="size-4" /></Link
                    ></Button>
                    <Button
                        v-else
                        variant="outline"
                        size="icon"
                        disabled
                        :aria-label="t('stage2.next_day')"
                        ><ChevronRight class="size-4"
                    /></Button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-44">
                        <WorklogDatePicker
                            :model-value="date"
                            :max-date="localToday"
                            :aria-label="t('stage2.choose_day')"
                            @update:model-value="changeDate"
                        />
                    </div>
                    <Button variant="outline" size="sm" as-child
                        ><Link :href="dayUrl(localToday)">{{ t('stage2.today') }}</Link></Button
                    >
                </div>
            </div>
            <div class="rounded-xl border bg-card p-3 sm:p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-muted-foreground">{{
                        weekLabel
                    }}</span>
                    <div class="flex gap-1">
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            as-child
                            :aria-label="t('stage2.previous_week')"
                            ><Link :href="dayUrl(previousWeek)"
                                ><ChevronLeft class="size-4" /></Link
                        ></Button>
                        <Button
                            v-if="canGoNextWeek"
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            as-child
                            :aria-label="t('stage2.next_week')"
                            ><Link :href="dayUrl(nextWeek)"
                                ><ChevronRight class="size-4" /></Link
                        ></Button>
                        <Button
                            v-else
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            disabled
                            :aria-label="t('stage2.next_week')"
                            ><ChevronRight class="size-4"
                        /></Button>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                    <Link
                        v-for="day in week"
                        :key="day.date"
                        :href="dayUrl(day.date)"
                        :aria-current="date === day.date ? 'date' : undefined"
                        :aria-disabled="day.date > localToday"
                        :tabindex="day.date > localToday ? -1 : undefined"
                        :class="[
                            'min-w-0 rounded-lg border px-1 py-2 text-center transition-colors sm:px-2 sm:py-3',
                            date === day.date
                                ? 'border-primary/50 bg-primary/5 ring-1 ring-primary/20'
                                : 'border-transparent hover:border-border hover:bg-muted/40',
                            day.date > localToday
                                ? 'pointer-events-none opacity-40'
                                : '',
                        ]"
                    >
                        <span
                            class="block text-[10px] text-muted-foreground sm:text-xs"
                            >{{
                                formatDay(day.date, { weekday: 'short' })
                            }}</span
                        >
                        <span
                            class="mt-1 block text-sm font-semibold tabular-nums sm:text-base"
                            >{{ formatDay(day.date, { day: 'numeric' }) }}</span
                        >
                        <span
                            class="mt-1 block truncate text-[10px] font-medium tabular-nums sm:text-xs"
                            >{{ duration(day.total_minutes) }}</span
                        >
                        <span
                            class="mt-2 block h-1 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                class="block h-full rounded-full bg-primary"
                                :style="{
                                    width: `${Math.min(100, (day.total_minutes / daily_limit_minutes) * 100)}%`,
                                }"
                            />
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <Card class="min-w-0">
                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="flex items-center gap-2"
                                ><CalendarDays class="size-4" /> {{ t('audit.time_entries') }}</CardTitle
                            >
                            <CardDescription
                                >{{ formatText('fix.worklog_for_day', { date: dayLabel, count: countLabel('entry', entries.total) }) }}</CardDescription
                            >
                        </div>
                        <Button
                            v-if="worklog_projects.length"
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="addEntry"
                            ><Plus class="size-4" /> {{ t('stage2.log_time') }}</Button
                        >
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="entries.data.length" class="divide-y">
                        <article
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="flex items-start gap-3 py-4 first:pt-0 last:pb-0"
                        >
                            <span
                                class="mt-0.5 hidden shrink-0 rounded-md bg-muted p-2 sm:inline-flex"
                                ><FolderKanban
                                    class="size-4 text-muted-foreground"
                            /></span>
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="text-xs text-muted-foreground">
                                    {{ entry.project.name }}
                                </div>
                                <Link
                                    :href="`/projects/${entry.project.id}/tasks/${entry.task.id}`"
                                    class="block text-sm leading-snug font-medium hover:underline"
                                    >{{ entry.task.reference }} ·
                                    {{ entry.task.title }}</Link
                                >
                                <p
                                    v-if="entry.description"
                                    class="text-sm break-words whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{ entry.description }}
                                </p>
                                <Badge
                                    v-if="entry.is_creative"
                                    variant="secondary"
                                    class="text-xs"
                                    >{{ t('stage2.creative_work') }}</Badge
                                >
                                <div
                                    v-if="entry.can_manage"
                                    class="flex items-center gap-1 pt-0.5"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs text-muted-foreground"
                                        @click="editEntry(entry)"
                                        ><Pencil class="size-3.5" />
                                        {{ t('stage2.edit') }}</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs text-muted-foreground hover:text-destructive"
                                        @click="deletingEntry = entry"
                                        ><Trash2 class="size-3.5" />
                                        {{ t('stage2.delete') }}</Button
                                    >
                                </div>
                            </div>
                            <div
                                class="flex shrink-0 items-center gap-1 text-sm font-semibold tabular-nums"
                            >
                                <Clock3
                                    class="size-3.5 text-muted-foreground"
                                />
                                {{ duration(entry.duration_minutes) }}
                            </div>
                        </article>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 py-12 text-center"
                    >
                        <Clock3 class="size-9 text-muted-foreground" />
                        <p class="font-medium">
                            {{ t('stage2.no_visible_time_entries_for_this_day') }}
                        </p>
                        <p class="max-w-sm text-sm text-muted-foreground">
                            {{ t('stage2.your_work_across_accessible_projects_will_appear_here') }}
                        </p>
                        <Button
                            v-if="worklog_projects.length"
                            variant="outline"
                            size="sm"
                            class="mt-2"
                            @click="addEntry"
                            ><Plus class="size-4" /> {{ t('stage2.add_first_entry') }}</Button
                        >
                        <Button
                            v-else
                            variant="outline"
                            size="sm"
                            as-child
                            class="mt-2"
                            ><Link href="/projects"
                                >{{ t('stage2.browse_projects') }}</Link
                            ></Button
                        >
                    </div>
                    <div
                        v-if="entries.last_page > 1"
                        class="mt-5 flex items-center justify-between gap-3 border-t pt-4"
                    >
                        <Button
                            v-if="entries.previous_page_url"
                            size="sm"
                            variant="outline"
                            as-child
                            ><Link :href="entries.previous_page_url"
                                >{{ t('stage2.previous') }}</Link
                            ></Button
                        >
                        <Button v-else size="sm" variant="outline" disabled
                            >{{ t('stage2.previous') }}</Button
                        >
                        <span class="text-xs text-muted-foreground"
                            >{{ formatText('fix.page_of', { page: entries.current_page, pages: entries.last_page }) }}</span
                        >
                        <Button
                            v-if="entries.next_page_url"
                            size="sm"
                            variant="outline"
                            as-child
                            ><Link :href="entries.next_page_url"
                                >{{ t('stage2.next') }}</Link
                            ></Button
                        >
                        <Button v-else size="sm" variant="outline" disabled
                            >{{ t('stage2.next') }}</Button
                        >
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:sticky lg:top-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Clock3 class="size-4" /> {{ t('stage2.daily_summary') }}</CardTitle
                    >
                    <CardDescription
                        >{{ t('audit.all_your_work_counts_toward_the_daily_limit_across_projects') }}</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <p class="text-xs text-muted-foreground">{{ t('stage2.logged_time') }}</p>
                        <p class="mt-1 text-3xl font-semibold tabular-nums">
                            {{ duration(total_minutes) }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >/ {{ duration(daily_limit_minutes) }}</span
                            >
                        </p>
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-valuenow="
                            Math.min(total_minutes, daily_limit_minutes)
                        "
                        :aria-valuemax="daily_limit_minutes"
                        aria-valuemin="0"
                        :aria-label="t('stage2.daily_logged_minutes')"
                    >
                        <div
                            class="h-full rounded-full bg-primary transition-all"
                            :style="{ width: `${completion}%` }"
                        />
                    </div>
                    <div class="flex justify-between border-t pt-4 text-sm">
                        <span class="text-muted-foreground">{{ t('stage2.remaining') }}</span
                        ><span class="font-semibold tabular-nums">{{
                            duration(remaining)
                        }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">{{ t('stage2.daily_limit') }}</span
                        ><span class="font-medium tabular-nums">{{
                            duration(daily_limit_minutes)
                        }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground"
                            >{{ t('stage2.visible_entries') }}</span
                        ><span class="font-medium tabular-nums">{{
                            entries.total
                        }}</span>
                    </div>
                    <p
                        v-if="hiddenMinutes"
                        class="flex gap-2 border-t pt-3 text-xs leading-relaxed text-muted-foreground"
                    >
                        <LockKeyhole class="mt-0.5 size-3.5 shrink-0" />
                        {{ formatText('fix.worklog_inaccessible', { duration: duration(hiddenMinutes) }) }}
                    </p>
                    <p
                        v-if="total_minutes > daily_limit_minutes"
                        class="text-xs text-destructive"
                    >
                        {{ t('stage2.historical_entries_exceed_the_current_daily_limit_new_entries_cannot_increa') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>

    <Dialog
        :open="editorOpen"
        @update:open="
            (open) => {
                if (!open) closeEditor();
            }
        "
    >
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-xl"
            :show-close-button="!form.processing"
        >
            <DialogHeader>
                <DialogTitle>{{
                    editing ? t('audit.edit_worklog') : t('audit.add_worklog')
                }}</DialogTitle>
                <DialogDescription
                    >{{ formatText('fix.worklog_record_limit', { duration: duration(daily_limit_minutes) }) }}</DialogDescription
                >
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="saveEntry">
                <template v-if="!editing">
                    <div class="grid gap-1.5">
                        <Label for="global-worklog-project">{{ t('stage2.project') }}</Label>
                        <Select v-model="projectId" :disabled="form.processing">
                            <SelectTrigger
                                id="global-worklog-project"
                                class="w-full"
                                ><SelectValue :placeholder="t('stage2.choose_a_project')"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="project in worklog_projects"
                                    :key="project.id"
                                    :value="String(project.id)"
                                    >{{ project.name }} ·
                                    {{ project.workspace_name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="global-worklog-search">{{ t('stage2.task') }}</Label>
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            />
                            <Input
                                id="global-worklog-search"
                                v-model="taskSearch"
                                class="pl-9"
                                maxlength="100"
                                :placeholder="t('stage2.search_by_task_title_or_number')"
                                :disabled="!projectId || form.processing"
                            />
                        </div>
                        <Select
                            v-model="taskId"
                            :disabled="
                                taskLoading ||
                                !taskOptions.length ||
                                form.processing
                            "
                        >
                            <SelectTrigger class="w-full"
                                ><SelectValue
                                    :placeholder="
                                        taskLoading
                                            ? t('fix.load_tasks')
                                            : t('fix.choose_task')
                                    "
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="task in taskOptions"
                                    :key="task.id"
                                    :value="String(task.id)"
                                    >{{ task.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <p v-if="taskError" class="text-xs text-destructive">
                            {{ taskError }}
                        </p>
                        <p
                            v-else-if="!taskLoading && !taskOptions.length"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('stage2.no_matching_tasks_try_another_search') }}
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            {{ t('stage2.showing_up_to_40_tasks_search_to_find_older_tasks') }}
                        </p>
                        <InputError :message="form.errors.task_id" />
                    </div>
                </template>
                <div v-else class="rounded-md bg-muted/50 px-3 py-2 text-sm">
                    <span class="block text-xs text-muted-foreground"
                        >{{ t('stage2.task') }}</span
                    >
                    <span class="font-medium"
                        >{{ editingEntry?.task.reference }} ·
                        {{ editingEntry?.task.title }}</span
                    >
                </div>
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_6rem]">
                    <div class="grid gap-1.5">
                        <Label for="global-worklog-date">{{ t('stage2.work_date') }}</Label>
                        <WorklogDatePicker
                            id="global-worklog-date"
                            v-model="form.work_date"
                            :max-date="localToday"
                            :disabled="form.processing"
                            :invalid="Boolean(form.errors.work_date)"
                        />
                        <InputError :message="form.errors.work_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="global-worklog-hours">{{ t('stage2.hours') }}</Label>
                        <Input
                            id="global-worklog-hours"
                            :model-value="form.hours"
                            @update:model-value="
                                (value) => (form.hours = Number(value))
                            "
                            type="number"
                            min="0"
                            max="8"
                            step="1"
                            required
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.hours" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="global-worklog-minutes">{{ t('stage2.minutes') }}</Label>
                        <Input
                            id="global-worklog-minutes"
                            :model-value="form.minutes"
                            @update:model-value="
                                (value) => (form.minutes = Number(value))
                            "
                            type="number"
                            min="0"
                            max="59"
                            step="1"
                            required
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.minutes" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="global-worklog-description"
                        >{{ t('stage2.work_performed_outcome') }}
                        <span class="font-normal text-muted-foreground"
                            >{{ t('stage2.optional') }}</span
                        ></Label
                    >
                    <Textarea
                        id="global-worklog-description"
                        v-model="form.description"
                        maxlength="4000"
                        rows="3"
                        :disabled="form.processing"
                        :placeholder="t('stage2.describe_what_you_completed_during_this_time')"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="global-worklog-creative"
                        :model-value="form.is_creative"
                        :disabled="form.processing"
                        @update:model-value="
                            (checked) => (form.is_creative = checked === true)
                        "
                    />
                    <Label for="global-worklog-creative" class="cursor-pointer"
                        >{{ t('stage2.creative_work') }}
                        <span class="font-normal text-muted-foreground"
                            >{{ t('stage2.optional') }}</span
                        ></Label
                    >
                </div>
                <InputError :message="form.errors.is_creative" />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="closeEditor"
                        >{{ t('stage2.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            (!editing && !taskId) ||
                            form.hours * 60 + form.minutes < 1
                        "
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                        />
                        {{ form.processing ? t('audit.saving') : t('audit.save_worklog') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="deletingEntry !== null"
        @update:open="
            (open) => {
                if (!open && !deleteForm.processing) deletingEntry = null;
            }
        "
    >
        <DialogContent :show-close-button="!deleteForm.processing">
            <DialogHeader>
                <DialogTitle>{{ t('stage2.delete_worklog_entry') }}</DialogTitle>
                <DialogDescription
                    >{{ formatText('fix.worklog_remove', { duration: duration(deletingEntry?.duration_minutes ?? 0), reference: deletingEntry?.task.reference ?? '' }) }}</DialogDescription
                >
            </DialogHeader>
            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="deleteForm.processing"
                    @click="deletingEntry = null"
                    >{{ t('stage2.cancel') }}</Button
                >
                <Button
                    variant="destructive"
                    :disabled="deleteForm.processing"
                    @click="removeEntry"
                    ><LoaderCircle
                        v-if="deleteForm.processing"
                        class="size-4 animate-spin"
                    />
                    {{ t('stage2.delete_entry') }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
