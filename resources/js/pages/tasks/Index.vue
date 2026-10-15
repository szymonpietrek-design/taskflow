<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, shallowRef, watch } from 'vue';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Circle,
    Clock3,
    ClipboardList,
    LoaderCircle,
    Plus,
    Search,
    SlidersHorizontal,
} from '@lucide/vue';
import type { DateValue } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    today,
} from '@internationalized/date';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    TaskListSummary,
    TaskParentSummary,
    TaskProjectSummary,
    TaskStatusSummary,
    TaskUserSummary,
} from '@/types/task';

const props = defineProps<{
    project: TaskProjectSummary;
    tasks: TaskListSummary[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        previous_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        q: string;
        status_id: string;
        priority: string;
        assignee_id: string;
        sort: string;
    };
    filter_assignees: TaskUserSummary[];
    parent_tasks: TaskParentSummary[];
    statuses: TaskStatusSummary[];
    assignees: TaskUserSummary[];
    can_create: boolean;
}>();

const indexUrl = `/projects/${props.project.id}/tasks`;
const filterForm = reactive({
    ...props.filters,
    sort: props.filters.sort || 'newest',
});
const filterProcessing = ref(false);
watch(
    () => props.filters,
    (filters) =>
        Object.assign(filterForm, filters, { sort: filters.sort || 'newest' }),
);

// Reka UI Select requires non-empty item values. The filter URL must still
// use empty strings for the unfiltered states expected by Laravel.
const filterStatus = computed({
    get: () => filterForm.status_id || 'all',
    set: (value: string) => {
        filterForm.status_id = value === 'all' ? '' : value;
    },
});
const filterPriority = computed({
    get: () => filterForm.priority || 'all',
    set: (value: string) => {
        filterForm.priority = value === 'all' ? '' : value;
    },
});
const filterAssignee = computed({
    get: () => filterForm.assignee_id || 'anyone',
    set: (value: string) => {
        filterForm.assignee_id = value === 'anyone' ? '' : value;
    },
});
const filterSort = computed({
    get: () => filterForm.sort || 'newest',
    set: (value: string) => {
        filterForm.sort = value;
    },
});
const hasActiveFilters = computed(() =>
    Boolean(
        filterForm.q.trim() ||
        filterForm.status_id ||
        filterForm.priority ||
        filterForm.assignee_id ||
        (filterForm.sort && filterForm.sort !== 'newest'),
    ),
);

function clearFilters() {
    if (filterProcessing.value) return;
    Object.assign(filterForm, {
        q: '',
        status_id: '',
        priority: '',
        assignee_id: '',
        sort: 'newest',
    });
    applyFilters();
}

function applyFilters() {
    if (filterProcessing.value) return;
    router.get(
        indexUrl,
        {
            q: filterForm.q.trim() || undefined,
            status_id: filterForm.status_id || undefined,
            priority: filterForm.priority || undefined,
            assignee_id: filterForm.assignee_id || undefined,
            sort: filterForm.sort !== 'newest' ? filterForm.sort : undefined,
        },
        {
            replace: true,
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                filterProcessing.value = true;
            },
            onFinish: () => {
                filterProcessing.value = false;
            },
        },
    );
}

const taskUrl = (id: number) => `/projects/${props.project.id}/tasks/${id}`;

// The project overview links straight to the existing quick-create form.
onMounted(() => {
    if (props.can_create && window.location.hash === '#new-task') {
        window.requestAnimationFrame(() => {
            document.getElementById('new-task')?.scrollIntoView({ block: 'start' });
        });
    }
});

// shadcn-vue Select and Calendar are controlled Vue components rather than
// native form inputs. Hidden inputs keep the request payload unchanged.
const newTaskType = ref('1');
const newTaskPriority = ref('2');
const newTaskStatus = ref('default');
const newTaskAssignee = ref('unassigned');
const newTaskParent = ref('none');
const newTaskDueDate = shallowRef<DateValue | undefined>();
const dueDateFormatter = computed(() => new DateFormatter(locale.value === 'pl' ? 'pl-PL' : 'en-US', { dateStyle: 'medium' }));

function resetNewTaskFields() {
    newTaskType.value = '1';
    newTaskPriority.value = '2';
    newTaskStatus.value = 'default';
    newTaskAssignee.value = 'unassigned';
    newTaskParent.value = 'none';
    newTaskDueDate.value = undefined;
}

function formatWorklogTime(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return (
        [hours > 0 ? `${hours}h` : '', minutes > 0 ? `${minutes}m` : '']
            .filter(Boolean)
            .join(' ') || '0m'
    );
}

function badgeVariant(
    category: TaskStatusSummary['category'],
): 'secondary' | 'outline' | 'default' {
    if (category === 'done') return 'default';
    if (category === 'canceled') return 'outline';
    return 'secondary';
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});
const { t, locale, displayLabel, formatText } = useI18n();
</script>

<template>
    <Head :title="`${project.name} · Tasks`" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <Link
                :href="`/projects/${project.id}`"
                class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> {{ project.name }}
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ t('stage2.tasks') }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ formatText('followup.track_work', { project: project.name, key: project.key }) }}
            </p>
        </div>

        <div
            class="grid items-start gap-6"
            :class="can_create ? 'xl:grid-cols-[minmax(0,1fr)_23rem]' : ''"
        >
            <section class="min-w-0 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-medium">{{ t('stage2.project_tasks') }}</h2>
                    <span class="text-xs text-muted-foreground"
                        >{{ pagination.total }}
                        {{ pagination.total === 1 ? t('followup.task_count_one') : t('followup.task_count_many') }}</span
                    >
                </div>

                <Card class="gap-0 p-4">
                    <form class="space-y-4" @submit.prevent="applyFilters">
                        <div
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <SlidersHorizontal class="size-4" /> {{ t('stage2.filters') }}
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-search">{{ t('stage2.search') }}</Label>
                                <Input
                                    id="task-search"
                                    v-model="filterForm.q"
                                    maxlength="100"
                                    :placeholder="`${t('stage2.title')}, ${t('stage2.description')}, ${project.key}-1`"
                                    :disabled="filterProcessing"
                                />
                            </div>
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-filter-status">{{ t('stage2.status') }}</Label>
                                <Select
                                    v-model="filterStatus"
                                    :disabled="filterProcessing"
                                >
                                    <SelectTrigger
                                        id="task-filter-status"
                                        class="w-full"
                                        ><SelectValue
                                            :placeholder="t('stage2.all_statuses')"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all"
                                            >{{ t('stage2.all_statuses') }}</SelectItem
                                        >
                                        <SelectItem
                                            v-for="status in statuses"
                                            :key="status.id"
                                            :value="String(status.id)"
                                            >{{ status.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-filter-priority"
                                    >{{ t('stage2.priority') }}</Label
                                >
                                <Select
                                    v-model="filterPriority"
                                    :disabled="filterProcessing"
                                >
                                    <SelectTrigger
                                        id="task-filter-priority"
                                        class="w-full"
                                        ><SelectValue
                                            :placeholder="t('stage2.all_priorities')"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all"
                                            >{{ t('stage2.all_priorities') }}</SelectItem
                                        >
                                        <SelectItem value="1">{{ t('stage2.low') }}</SelectItem>
                                        <SelectItem value="2"
                                            >{{ t('stage2.normal') }}</SelectItem
                                        >
                                        <SelectItem value="3">{{ t('stage2.high') }}</SelectItem>
                                        <SelectItem value="4"
                                            >{{ t('stage2.urgent') }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-filter-assignee"
                                    >{{ t('stage2.assignee') }}</Label
                                >
                                <Select
                                    v-model="filterAssignee"
                                    :disabled="filterProcessing"
                                >
                                    <SelectTrigger
                                        id="task-filter-assignee"
                                        class="w-full"
                                        ><SelectValue :placeholder="t('stage2.anyone')"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="anyone"
                                            >{{ t('stage2.anyone') }}</SelectItem
                                        >
                                        <SelectItem value="unassigned"
                                            >{{ t('stage2.unassigned') }}</SelectItem
                                        >
                                        <SelectItem
                                            v-for="person in filter_assignees"
                                            :key="person.id"
                                            :value="String(person.id)"
                                            >{{ person.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div
                            class="flex flex-wrap items-end justify-between gap-3"
                        >
                            <div class="grid w-full min-w-0 gap-1.5 sm:w-48">
                                <Label for="task-filter-sort">{{ t('stage2.sort_by') }}</Label>
                                <Select
                                    v-model="filterSort"
                                    :disabled="filterProcessing"
                                >
                                    <SelectTrigger
                                        id="task-filter-sort"
                                        class="w-full"
                                        ><SelectValue :placeholder="t('stage2.sort_by')"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="newest"
                                            >{{ t('stage2.newest_first') }}</SelectItem
                                        >
                                        <SelectItem value="oldest"
                                            >{{ t('stage2.oldest_first') }}</SelectItem
                                        >
                                        <SelectItem value="priority"
                                            >{{ t('stage2.highest_priority') }}</SelectItem
                                        >
                                        <SelectItem value="due_date"
                                            >{{ t('stage2.due_date') }}</SelectItem
                                        >
                                        <SelectItem value="title"
                                            >{{ t('stage2.title_a_z') }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    :disabled="
                                        filterProcessing || !hasActiveFilters
                                    "
                                    @click="clearFilters"
                                    >{{ t('stage2.clear_filters') }}</Button
                                >
                                <Button
                                    type="submit"
                                    :disabled="filterProcessing"
                                >
                                    <LoaderCircle
                                        v-if="filterProcessing"
                                        class="size-4 animate-spin"
                                    />
                                    <Search v-else class="size-4" />
                                    {{
                                        filterProcessing ? t('followup.applying') : t('followup.apply')
                                    }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </Card>

                <TooltipProvider v-if="tasks.length">
                    <Card class="gap-0 overflow-hidden py-0">
                        <div class="overflow-x-auto">
                            <table
                                class="w-full min-w-[39rem] text-left text-sm"
                            >
                                <thead
                                    class="border-b bg-muted/30 text-xs text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t('stage2.task') }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t('stage2.status') }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t('stage2.priority') }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t('stage2.assignee') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="task in tasks"
                                        :key="task.id"
                                        class="transition-colors hover:bg-muted/20"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="flex items-start gap-2">
                                                <Link
                                                    :href="taskUrl(task.id)"
                                                    :aria-label="formatText('audit.view_task', { reference: task.reference })"
                                                    class="mt-0.5 shrink-0 text-muted-foreground"
                                                >
                                                    <CheckCircle2
                                                        v-if="
                                                            task.status
                                                                .category ===
                                                            'done'
                                                        "
                                                        class="size-4"
                                                    />
                                                    <Circle
                                                        v-else
                                                        class="size-4"
                                                    />
                                                </Link>
                                                <div class="min-w-0">
                                                    <Link
                                                        :href="taskUrl(task.id)"
                                                        class="block font-medium hover:underline"
                                                        >{{ task.title }}</Link
                                                    >
                                                    <div
                                                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                                    >
                                                        <Link
                                                            :href="
                                                                taskUrl(task.id)
                                                            "
                                                            class="hover:underline"
                                                        >
                                                            {{ task.reference }}
                                                            ·
                                                            {{
                                                                displayLabel(task.type_label)
                                                            }}
                                                            <template
                                                                v-if="
                                                                    task.parent
                                                                "
                                                            >
                                                                · {{ formatText('fix.subtask_of', { reference: task.parent.reference }) }}</template
                                                            >
                                                        </Link>
                                                        <Tooltip
                                                            v-if="
                                                                task.worklog_entries_count >
                                                                0
                                                            "
                                                        >
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <span
                                                                    tabindex="0"
                                                                    class="inline-flex cursor-help items-center gap-1 rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                                    :aria-label="formatText('audit.worklog_label', { duration: formatWorklogTime(task.worklog_total_minutes), count: task.worklog_entries_count })"
                                                                >
                                                                    <Clock3
                                                                        class="size-3.5"
                                                                        aria-hidden="true"
                                                                    />
                                                                    {{
                                                                        formatWorklogTime(
                                                                            task.worklog_total_minutes,
                                                                        )
                                                                    }}
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <div
                                                                    class="space-y-1"
                                                                >
                                                                    <p
                                                                        class="font-medium"
                                                                    >
                                                                        {{ t('stage2.worklog') }}
                                                                    </p>
                                                                    <p>
                                                                        {{ t('fix.total_time') }}
                                                                        {{
                                                                            formatWorklogTime(
                                                                                task.worklog_total_minutes,
                                                                            )
                                                                        }}
                                                                    </p>
                                                                    <p>
                                                                        {{ t('audit.task_entries_label') }}
                                                                        {{
                                                                            task.worklog_entries_count
                                                                        }}
                                                                    </p>
                                                                </div>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    badgeVariant(
                                                        task.status.category,
                                                    )
                                                "
                                                >{{ task.status.name }}</Badge
                                            >
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ displayLabel(task.priority_label) }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{
                                                task.assignee?.name ??
                                                t('fix.no_assignee')
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </TooltipProvider>
                <Card v-else class="border-dashed">
                    <CardContent
                        class="flex flex-col items-center gap-2 py-12 text-center"
                    >
                        <ClipboardList class="size-7 text-muted-foreground" />
                        <p class="font-medium">
                            {{
                                pagination.total === 0 &&
                                (filters.q ||
                                    filters.status_id ||
                                    filters.priority ||
                                    filters.assignee_id)
                                    ? t('fix.no_matches')
                                    : t('fix.no_tasks')
                            }}
                        </p>
                        <p class="max-w-sm text-sm text-muted-foreground">
                            {{
                                filters.q ||
                                filters.status_id ||
                                filters.priority ||
                                filters.assignee_id
                                    ? t('fix.clear_search')
                                    : can_create
                                      ? t('fix.create_first_task')
                                      : t('fix.tasks_appear')
                            }}
                        </p>
                        <Link
                            v-if="
                                filters.q ||
                                filters.status_id ||
                                filters.priority ||
                                filters.assignee_id
                            "
                            :href="indexUrl"
                            class="text-sm underline"
                            >{{ t('stage2.clear_filters') }}</Link
                        >
                    </CardContent>
                </Card>
                <div
                    v-if="pagination.total > 0"
                    class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
                >
                    <span
                        >{{ formatText('followup.showing', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total }) }}</span
                    >
                    <nav
                        v-if="pagination.last_page > 1"
                        class="flex items-center gap-3"
                        :aria-label="t('stage2.task_pages')"
                    >
                        <Link
                            v-if="pagination.previous_page_url"
                            :href="pagination.previous_page_url"
                            preserve-scroll
                            class="rounded-md border px-3 py-1.5 text-foreground hover:bg-muted"
                            >{{ t('stage2.previous') }}</Link
                        >
                        <span
                            v-else
                            aria-disabled="true"
                            class="rounded-md border px-3 py-1.5 opacity-40"
                            >{{ t('stage2.previous') }}</span
                        >
                        <span
                            >{{ formatText('audit.count_pages', { page: pagination.current_page, pages: pagination.last_page }) }}</span
                        >
                        <Link
                            v-if="pagination.next_page_url"
                            :href="pagination.next_page_url"
                            preserve-scroll
                            class="rounded-md border px-3 py-1.5 text-foreground hover:bg-muted"
                            >{{ t('stage2.next') }}</Link
                        >
                        <span
                            v-else
                            aria-disabled="true"
                            class="rounded-md border px-3 py-1.5 opacity-40"
                            >{{ t('stage2.next') }}</span
                        >
                    </nav>
                </div>
            </section>

            <Card v-if="can_create" id="new-task" class="h-fit scroll-mt-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Plus class="size-4" /> {{ t('stage2.new_task') }}</CardTitle
                    >
                    <CardDescription
                        >{{ t('followup.task_numbers') }}</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Form
                        :action="`/projects/${project.id}/tasks`"
                        method="post"
                        class="space-y-4"
                        reset-on-success
                        @success="resetNewTaskFields"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="task-title">{{ t('stage2.title') }}</Label>
                            <Input
                                id="task-title"
                                name="title"
                                maxlength="200"
                                required
                                :placeholder="t('stage2.implement_dashboard')"
                            />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-description"
                                >{{ t('stage2.description') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <textarea
                                id="task-description"
                                name="description"
                                rows="3"
                                maxlength="10000"
                                :placeholder="t('stage2.optional_details')"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid min-w-0 gap-2">
                                <Label for="task-type">{{ t('stage2.type') }}</Label>
                                <input
                                    type="hidden"
                                    name="type"
                                    :value="newTaskType"
                                />
                                <Select
                                    v-model="newTaskType"
                                    :disabled="processing"
                                >
                                    <SelectTrigger id="task-type" class="w-full"
                                        ><SelectValue :placeholder="t('stage2.select_type')"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">{{ t('stage2.task') }}</SelectItem>
                                        <SelectItem value="2"
                                            >{{ t('stage2.feature') }}</SelectItem
                                        >
                                        <SelectItem value="3"
                                            >{{ t('stage2.improvement') }}</SelectItem
                                        >
                                        <SelectItem value="4">{{ t('stage2.bug') }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.type" />
                            </div>
                            <div class="grid min-w-0 gap-2">
                                <Label for="task-priority">{{ t('stage2.priority') }}</Label>
                                <input
                                    type="hidden"
                                    name="priority"
                                    :value="newTaskPriority"
                                />
                                <Select
                                    v-model="newTaskPriority"
                                    :disabled="processing"
                                >
                                    <SelectTrigger
                                        id="task-priority"
                                        class="w-full"
                                        ><SelectValue
                                            :placeholder="t('stage2.select_priority')"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">{{ t('stage2.low') }}</SelectItem>
                                        <SelectItem value="2"
                                            >{{ t('stage2.normal') }}</SelectItem
                                        >
                                        <SelectItem value="3">{{ t('stage2.high') }}</SelectItem>
                                        <SelectItem value="4"
                                            >{{ t('stage2.urgent') }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.priority" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-status"
                                >{{ t('stage2.status') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <input
                                type="hidden"
                                name="status_id"
                                :value="
                                    newTaskStatus === 'default'
                                        ? ''
                                        : newTaskStatus
                                "
                            />
                            <Select
                                v-model="newTaskStatus"
                                :disabled="processing"
                            >
                                <SelectTrigger id="task-status" class="w-full"
                                    ><SelectValue :placeholder="t('stage2.select_status')"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="default"
                                        >{{ t('stage2.default_todo') }}</SelectItem
                                    >
                                    <SelectItem
                                        v-for="status in statuses"
                                        :key="status.id"
                                        :value="String(status.id)"
                                        >{{ status.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.status_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-assignee"
                                >{{ t('stage2.assignee') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <input
                                type="hidden"
                                name="assignee_id"
                                :value="
                                    newTaskAssignee === 'unassigned'
                                        ? ''
                                        : newTaskAssignee
                                "
                            />
                            <Select
                                v-model="newTaskAssignee"
                                :disabled="processing"
                            >
                                <SelectTrigger id="task-assignee" class="w-full"
                                    ><SelectValue :placeholder="t('stage2.select_assignee')"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unassigned"
                                        >{{ t('stage2.unassigned') }}</SelectItem
                                    >
                                    <SelectItem
                                        v-for="person in assignees"
                                        :key="person.id"
                                        :value="String(person.id)"
                                        >{{ person.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.assignee_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-parent"
                                >{{ t('stage2.parent_task') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <input
                                type="hidden"
                                name="parent_id"
                                :value="
                                    newTaskParent === 'none'
                                        ? ''
                                        : newTaskParent
                                "
                            />
                            <Select
                                v-model="newTaskParent"
                                :disabled="processing"
                            >
                                <SelectTrigger id="task-parent" class="w-full"
                                    ><SelectValue
                                        :placeholder="t('stage2.select_parent_task')"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none"
                                        >{{ t('stage2.none_regular_task') }}</SelectItem
                                    >
                                    <SelectItem
                                        v-for="task in parent_tasks"
                                        :key="task.id"
                                        :value="String(task.id)"
                                        >{{ task.reference }} —
                                        {{ task.title }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.parent_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-due-date"
                                >{{ t('stage2.due_date') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <input
                                type="hidden"
                                name="due_date"
                                :value="newTaskDueDate?.toString() ?? ''"
                            />
                            <Popover v-slot="{ close }">
                                <PopoverTrigger as-child>
                                    <Button
                                        id="task-due-date"
                                        type="button"
                                        variant="outline"
                                        class="w-full justify-between text-left font-normal"
                                        :class="
                                            !newTaskDueDate &&
                                            'text-muted-foreground'
                                        "
                                        :disabled="processing"
                                    >
                                        <span>{{
                                            newTaskDueDate
                                                ? dueDateFormatter.format(
                                                      newTaskDueDate.toDate(
                                                          getLocalTimeZone(),
                                                      ),
                                                  )
                                                : t('followup.date')
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
                                        v-model="newTaskDueDate"
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
                                            :disabled="!newTaskDueDate"
                                            @click="
                                                newTaskDueDate = undefined;
                                                close();
                                            "
                                        >
                                            {{ t('stage2.clear_date') }}
                                        </Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                            <InputError :message="errors.due_date" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="processing"
                            class="w-full"
                            ><LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            /><Plus v-else class="size-4" />
                            {{
                                processing ? t('followup.creating') : t('stage2.create_task')
                            }}</Button
                        >
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
