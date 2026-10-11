<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, shallowRef, watch } from 'vue';
import { ArrowLeft, CalendarDays, CheckCircle2, Circle, Clock3, ClipboardList, LoaderCircle, Plus, Search, SlidersHorizontal } from '@lucide/vue';
import type { DateValue } from '@internationalized/date';
import { DateFormatter, getLocalTimeZone, today } from '@internationalized/date';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TaskListSummary, TaskParentSummary, TaskProjectSummary, TaskStatusSummary, TaskUserSummary } from '@/types/task';

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
const filterForm = reactive({ ...props.filters, sort: props.filters.sort || 'newest' });
const filterProcessing = ref(false);
watch(() => props.filters, (filters) => Object.assign(filterForm, filters, { sort: filters.sort || 'newest' }));

// Reka UI Select requires non-empty item values. The filter URL must still
// use empty strings for the unfiltered states expected by Laravel.
const filterStatus = computed({
    get: () => filterForm.status_id || 'all',
    set: (value: string) => { filterForm.status_id = value === 'all' ? '' : value; },
});
const filterPriority = computed({
    get: () => filterForm.priority || 'all',
    set: (value: string) => { filterForm.priority = value === 'all' ? '' : value; },
});
const filterAssignee = computed({
    get: () => filterForm.assignee_id || 'anyone',
    set: (value: string) => { filterForm.assignee_id = value === 'anyone' ? '' : value; },
});
const filterSort = computed({
    get: () => filterForm.sort || 'newest',
    set: (value: string) => { filterForm.sort = value; },
});
const hasActiveFilters = computed(() => Boolean(
    filterForm.q.trim() || filterForm.status_id || filterForm.priority || filterForm.assignee_id || (filterForm.sort && filterForm.sort !== 'newest')
));

function clearFilters() {
    if (filterProcessing.value) return;
    Object.assign(filterForm, { q: '', status_id: '', priority: '', assignee_id: '', sort: 'newest' });
    applyFilters();
}

function applyFilters() {
    if (filterProcessing.value) return;
    router.get(indexUrl, {
        q: filterForm.q.trim() || undefined,
        status_id: filterForm.status_id || undefined,
        priority: filterForm.priority || undefined,
        assignee_id: filterForm.assignee_id || undefined,
        sort: filterForm.sort !== 'newest' ? filterForm.sort : undefined,
    }, {
        replace: true, preserveState: true, preserveScroll: true,
        onStart: () => { filterProcessing.value = true; },
        onFinish: () => { filterProcessing.value = false; },
    });
}

const taskUrl = (id: number) => `/projects/${props.project.id}/tasks/${id}`;

// shadcn-vue Select and Calendar are controlled Vue components rather than
// native form inputs. Hidden inputs keep the request payload unchanged.
const newTaskType = ref('1');
const newTaskPriority = ref('2');
const newTaskStatus = ref('default');
const newTaskAssignee = ref('unassigned');
const newTaskParent = ref('none');
const newTaskDueDate = shallowRef<DateValue | undefined>();
const dueDateFormatter = new DateFormatter('en-US', { dateStyle: 'medium' });

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
    return [hours > 0 ? `${hours}h` : '', minutes > 0 ? `${minutes}m` : ''].filter(Boolean).join(' ') || '0m';
}

function badgeVariant(category: TaskStatusSummary['category']): 'secondary' | 'outline' | 'default' {
    if (category === 'done') return 'default';
    if (category === 'canceled') return 'outline';
    return 'secondary';
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});
</script>

<template>
    <Head :title="`${project.name} · Tasks`" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <Link :href="`/projects/${project.id}`" class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ project.name }}
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">Tasks</h1>
            <p class="mt-1 text-sm text-muted-foreground">Track work in {{ project.name }} ({{ project.key }}).</p>
        </div>

        <div class="grid items-start gap-6" :class="can_create ? 'xl:grid-cols-[minmax(0,1fr)_23rem]' : ''">
            <section class="min-w-0 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-medium">Project tasks</h2>
                    <span class="text-xs text-muted-foreground">{{ pagination.total }} {{ pagination.total === 1 ? 'task' : 'tasks' }}</span>
                </div>

                <Card class="gap-0 p-4">
                    <form class="space-y-4" @submit.prevent="applyFilters">
                        <div class="flex items-center gap-2 text-sm font-medium"><SlidersHorizontal class="size-4" /> Filters</div>
                        <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-search">Search</Label>
                                <Input id="task-search" v-model="filterForm.q" maxlength="100" :placeholder="`Title, description, ${project.key}-1`" :disabled="filterProcessing" />
                            </div>
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-filter-status">Status</Label>
                                <Select v-model="filterStatus" :disabled="filterProcessing">
                                    <SelectTrigger id="task-filter-status" class="w-full"><SelectValue placeholder="All statuses" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All statuses</SelectItem>
                                        <SelectItem v-for="status in statuses" :key="status.id" :value="String(status.id)">{{ status.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-filter-priority">Priority</Label>
                                <Select v-model="filterPriority" :disabled="filterProcessing">
                                    <SelectTrigger id="task-filter-priority" class="w-full"><SelectValue placeholder="All priorities" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All priorities</SelectItem>
                                        <SelectItem value="1">Low</SelectItem>
                                        <SelectItem value="2">Normal</SelectItem>
                                        <SelectItem value="3">High</SelectItem>
                                        <SelectItem value="4">Urgent</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid min-w-0 gap-1.5">
                                <Label for="task-filter-assignee">Assignee</Label>
                                <Select v-model="filterAssignee" :disabled="filterProcessing">
                                    <SelectTrigger id="task-filter-assignee" class="w-full"><SelectValue placeholder="Anyone" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="anyone">Anyone</SelectItem>
                                        <SelectItem value="unassigned">Unassigned</SelectItem>
                                        <SelectItem v-for="person in filter_assignees" :key="person.id" :value="String(person.id)">{{ person.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div class="grid w-full min-w-0 gap-1.5 sm:w-48">
                                <Label for="task-filter-sort">Sort by</Label>
                                <Select v-model="filterSort" :disabled="filterProcessing">
                                    <SelectTrigger id="task-filter-sort" class="w-full"><SelectValue placeholder="Sort by" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="newest">Newest first</SelectItem>
                                        <SelectItem value="oldest">Oldest first</SelectItem>
                                        <SelectItem value="priority">Highest priority</SelectItem>
                                        <SelectItem value="due_date">Due date</SelectItem>
                                        <SelectItem value="title">Title A–Z</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button type="button" variant="ghost" :disabled="filterProcessing || !hasActiveFilters" @click="clearFilters">Clear filters</Button>
                                <Button type="submit" :disabled="filterProcessing">
                                    <LoaderCircle v-if="filterProcessing" class="size-4 animate-spin" />
                                    <Search v-else class="size-4" />
                                    {{ filterProcessing ? 'Applying…' : 'Apply' }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </Card>

                <TooltipProvider v-if="tasks.length">
                    <Card class="gap-0 overflow-hidden py-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[39rem] text-left text-sm">
                            <thead class="border-b bg-muted/30 text-xs text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Task</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Priority</th>
                                    <th class="px-4 py-3 font-medium">Assignee</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="task in tasks" :key="task.id" class="transition-colors hover:bg-muted/20">
                                    <td class="px-4 py-3">
                                        <div class="flex items-start gap-2">
                                            <Link :href="taskUrl(task.id)" :aria-label="`View ${task.reference}`" class="mt-0.5 shrink-0 text-muted-foreground">
                                                <CheckCircle2 v-if="task.status.category === 'done'" class="size-4" />
                                                <Circle v-else class="size-4" />
                                            </Link>
                                            <div class="min-w-0">
                                                <Link :href="taskUrl(task.id)" class="block font-medium hover:underline">{{ task.title }}</Link>
                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                                    <Link :href="taskUrl(task.id)" class="hover:underline">
                                                        {{ task.reference }} · {{ task.type_label }}
                                                        <template v-if="task.parent"> · Subtask of {{ task.parent.reference }}</template>
                                                    </Link>
                                                    <Tooltip v-if="task.worklog_entries_count > 0">
                                                        <TooltipTrigger as-child>
                                                            <span
                                                                tabindex="0"
                                                                class="inline-flex cursor-help items-center gap-1 rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                                :aria-label="`Worklog: ${formatWorklogTime(task.worklog_total_minutes)}, ${task.worklog_entries_count} ${task.worklog_entries_count === 1 ? 'entry' : 'entries'}`"
                                                            >
                                                                <Clock3 class="size-3.5" aria-hidden="true" />
                                                                {{ formatWorklogTime(task.worklog_total_minutes) }}
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <div class="space-y-1">
                                                                <p class="font-medium">Worklog</p>
                                                                <p>Total time: {{ formatWorklogTime(task.worklog_total_minutes) }}</p>
                                                                <p>Entries: {{ task.worklog_entries_count }}</p>
                                                            </div>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><Badge :variant="badgeVariant(task.status.category)">{{ task.status.name }}</Badge></td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ task.priority_label }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ task.assignee?.name ?? 'Unassigned' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    </Card>
                </TooltipProvider>
                <Card v-else class="border-dashed">
                    <CardContent class="flex flex-col items-center gap-2 py-12 text-center">
                        <ClipboardList class="size-7 text-muted-foreground" />
                        <p class="font-medium">{{ pagination.total === 0 && (filters.q || filters.status_id || filters.priority || filters.assignee_id) ? 'No matching tasks' : 'No tasks yet' }}</p>
                        <p class="max-w-sm text-sm text-muted-foreground">{{ filters.q || filters.status_id || filters.priority || filters.assignee_id ? 'Try changing or clearing your filters.' : (can_create ? 'Create the first task to start tracking work.' : 'Tasks will appear here when project members add them.') }}</p>
                        <Link v-if="filters.q || filters.status_id || filters.priority || filters.assignee_id" :href="indexUrl" class="text-sm underline">Clear filters</Link>
                    </CardContent>
                </Card>
                <div v-if="pagination.total > 0" class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <span>Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</span>
                    <nav v-if="pagination.last_page > 1" class="flex items-center gap-3" aria-label="Task pages">
                        <Link v-if="pagination.previous_page_url" :href="pagination.previous_page_url" preserve-scroll class="rounded-md border px-3 py-1.5 text-foreground hover:bg-muted">Previous</Link>
                        <span v-else aria-disabled="true" class="rounded-md border px-3 py-1.5 opacity-40">Previous</span>
                        <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                        <Link v-if="pagination.next_page_url" :href="pagination.next_page_url" preserve-scroll class="rounded-md border px-3 py-1.5 text-foreground hover:bg-muted">Next</Link>
                        <span v-else aria-disabled="true" class="rounded-md border px-3 py-1.5 opacity-40">Next</span>
                    </nav>
                </div>
            </section>

            <Card v-if="can_create" class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><Plus class="size-4" /> New task</CardTitle>
                    <CardDescription>Task numbers are assigned automatically.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Form :action="`/projects/${project.id}/tasks`" method="post" class="space-y-4" reset-on-success @success="resetNewTaskFields" v-slot="{ errors, processing }">
                        <div class="grid gap-2">
                            <Label for="task-title">Title</Label>
                            <Input id="task-title" name="title" maxlength="200" required placeholder="Implement dashboard" />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-description">Description <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <textarea id="task-description" name="description" rows="3" maxlength="10000" placeholder="Optional details" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid min-w-0 gap-2">
                                <Label for="task-type">Type</Label>
                                <input type="hidden" name="type" :value="newTaskType" />
                                <Select v-model="newTaskType" :disabled="processing">
                                    <SelectTrigger id="task-type" class="w-full"><SelectValue placeholder="Select type" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">Task</SelectItem>
                                        <SelectItem value="2">Feature</SelectItem>
                                        <SelectItem value="3">Improvement</SelectItem>
                                        <SelectItem value="4">Bug</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.type" />
                            </div>
                            <div class="grid min-w-0 gap-2">
                                <Label for="task-priority">Priority</Label>
                                <input type="hidden" name="priority" :value="newTaskPriority" />
                                <Select v-model="newTaskPriority" :disabled="processing">
                                    <SelectTrigger id="task-priority" class="w-full"><SelectValue placeholder="Select priority" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">Low</SelectItem>
                                        <SelectItem value="2">Normal</SelectItem>
                                        <SelectItem value="3">High</SelectItem>
                                        <SelectItem value="4">Urgent</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.priority" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-status">Status <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <input type="hidden" name="status_id" :value="newTaskStatus === 'default' ? '' : newTaskStatus" />
                            <Select v-model="newTaskStatus" :disabled="processing">
                                <SelectTrigger id="task-status" class="w-full"><SelectValue placeholder="Select status" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="default">Default (Todo)</SelectItem>
                                    <SelectItem v-for="status in statuses" :key="status.id" :value="String(status.id)">{{ status.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.status_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-assignee">Assignee <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <input type="hidden" name="assignee_id" :value="newTaskAssignee === 'unassigned' ? '' : newTaskAssignee" />
                            <Select v-model="newTaskAssignee" :disabled="processing">
                                <SelectTrigger id="task-assignee" class="w-full"><SelectValue placeholder="Select assignee" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unassigned">Unassigned</SelectItem>
                                    <SelectItem v-for="person in assignees" :key="person.id" :value="String(person.id)">{{ person.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.assignee_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-parent">Parent task <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <input type="hidden" name="parent_id" :value="newTaskParent === 'none' ? '' : newTaskParent" />
                            <Select v-model="newTaskParent" :disabled="processing">
                                <SelectTrigger id="task-parent" class="w-full"><SelectValue placeholder="Select parent task" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None (regular task)</SelectItem>
                                    <SelectItem v-for="task in parent_tasks" :key="task.id" :value="String(task.id)">{{ task.reference }} — {{ task.title }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.parent_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-due-date">Due date <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <input type="hidden" name="due_date" :value="newTaskDueDate?.toString() ?? ''" />
                            <Popover v-slot="{ close }">
                                <PopoverTrigger as-child>
                                    <Button
                                        id="task-due-date"
                                        type="button"
                                        variant="outline"
                                        class="w-full justify-between text-left font-normal"
                                        :class="!newTaskDueDate && 'text-muted-foreground'"
                                        :disabled="processing"
                                    >
                                        <span>{{ newTaskDueDate ? dueDateFormatter.format(newTaskDueDate.toDate(getLocalTimeZone())) : 'Select a date' }}</span>
                                        <CalendarDays class="size-4 shrink-0 opacity-70" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="start" class="w-auto p-0">
                                    <Calendar
                                        v-model="newTaskDueDate"
                                        :default-placeholder="today(getLocalTimeZone())"
                                        layout="month-and-year"
                                        initial-focus
                                        @update:model-value="close"
                                    />
                                    <div class="border-t p-2">
                                        <Button type="button" variant="ghost" size="sm" class="w-full" :disabled="!newTaskDueDate" @click="newTaskDueDate = undefined; close()">
                                            Clear date
                                        </Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                            <InputError :message="errors.due_date" />
                        </div>
                        <Button type="submit" :disabled="processing" class="w-full"><LoaderCircle v-if="processing" class="size-4 animate-spin" /><Plus v-else class="size-4" /> {{ processing ? 'Creating task…' : 'Create task' }}</Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
