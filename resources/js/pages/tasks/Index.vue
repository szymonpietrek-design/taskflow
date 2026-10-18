<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    ArrowLeft,
    Clock3,
    ClipboardList,
    LoaderCircle,
    Plus,
    Search,
    SlidersHorizontal,
} from '@lucide/vue';
import CreateTaskDialog from '@/components/tasks/CreateTaskDialog.vue';
import TaskStatusIcon from '@/components/tasks/TaskStatusIcon.vue';
import TaskTypeLabel from '@/components/tasks/TaskTypeLabel.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { taskPriorityDotClass, taskStatusBadgeClass } from '@/lib/taskVisuals';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
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

// Preserve existing deep links while opening the shared dialog instead of scrolling.
const isCreateDialogOpen = ref(false);
function openCreateDialog(): void {
    if (!props.can_create) return;
    isCreateDialogOpen.value = true;
}
function openFromHash(): void {
    if (window.location.hash === '#new-task') openCreateDialog();
}
onMounted(() => {
    openFromHash();
    window.addEventListener('hashchange', openFromHash);
});
onUnmounted(() => window.removeEventListener('hashchange', openFromHash));

function formatWorklogTime(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return (
        [hours > 0 ? `${hours}h` : '', minutes > 0 ? `${minutes}m` : '']
            .filter(Boolean)
            .join(' ') || '0m'
    );
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});
const { t, displayLabel, formatText } = useI18n();
</script>

<template>
    <Head :title="`${project.name} · ${t('stage2.tasks')}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
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
            <Button v-if="can_create" type="button" @click="openCreateDialog">
                <Plus class="size-4" /> {{ t('stage2.new_task') }}
            </Button>
        </div>

        <div class="min-w-0">
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
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                                class="w-full min-w-[43rem] text-left text-sm"
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
                                                    class="mt-0.5 inline-flex shrink-0 rounded-sm text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                >
                                                    <TaskStatusIcon :category="task.status.category" />
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
                                                            <TaskTypeLabel :type="task.type" />
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
                                                variant="outline"
                                                :class="taskStatusBadgeClass(task.status.category)"
                                            >{{ displayLabel(task.status.name) }}</Badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-2 whitespace-nowrap text-muted-foreground">
                                                <span
                                                    class="size-2 shrink-0 rounded-full"
                                                    :class="taskPriorityDotClass(task.priority)"
                                                    aria-hidden="true"
                                                />
                                                {{ displayLabel(task.priority_label) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="task.assignee" class="flex min-w-0 items-center gap-2">
                                                <UserAvatar
                                                    :user-id="task.assignee.id"
                                                    :name="task.assignee.name"
                                                    small
                                                />
                                                <span class="truncate text-muted-foreground" :title="task.assignee.name">
                                                    {{ task.assignee.name }}
                                                </span>
                                            </div>
                                            <span v-else class="text-muted-foreground">{{ t('fix.no_assignee') }}</span>
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
        </div>

        <CreateTaskDialog
            v-if="can_create"
            v-model:open="isCreateDialogOpen"
            :project-id="project.id"
            :statuses="statuses"
            :assignees="assignees"
            :parent-tasks="parent_tasks"
        />
    </div>
</template>
