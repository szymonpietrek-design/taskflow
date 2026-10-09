<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { ArrowLeft, CheckCircle2, Circle, ClipboardList, Plus, Search, SlidersHorizontal } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TaskParentSummary, TaskProjectSummary, TaskStatusSummary, TaskSummary, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    project: TaskProjectSummary;
    tasks: TaskSummary[];
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
const filterForm = reactive({ ...props.filters });
watch(() => props.filters, (filters) => Object.assign(filterForm, filters));

function applyFilters() {
    router.get(indexUrl, {
        q: filterForm.q.trim() || undefined,
        status_id: filterForm.status_id || undefined,
        priority: filterForm.priority || undefined,
        assignee_id: filterForm.assignee_id || undefined,
        sort: filterForm.sort !== 'newest' ? filterForm.sort : undefined,
    }, { replace: true, preserveState: true, preserveScroll: true });
}

const taskUrl = (id: number) => `/projects/${props.project.id}/tasks/${id}`;
const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

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
                    <form class="space-y-3" @submit.prevent="applyFilters">
                        <div class="flex items-center gap-2 text-sm font-medium"><SlidersHorizontal class="size-4" /> Filters</div>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-4">
                            <div class="grid gap-1.5">
                                <Label for="task-search">Search</Label>
                                <Input id="task-search" v-model="filterForm.q" maxlength="100" placeholder="Title, description, APP-1" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="task-filter-status">Status</Label>
                                <select id="task-filter-status" v-model="filterForm.status_id" :class="selectClass">
                                    <option value="">All statuses</option>
                                    <option v-for="status in statuses" :key="status.id" :value="String(status.id)">{{ status.name }}</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="task-filter-priority">Priority</Label>
                                <select id="task-filter-priority" v-model="filterForm.priority" :class="selectClass">
                                    <option value="">All priorities</option>
                                    <option value="1">Low</option>
                                    <option value="2">Normal</option>
                                    <option value="3">High</option>
                                    <option value="4">Urgent</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="task-filter-assignee">Assignee</Label>
                                <select id="task-filter-assignee" v-model="filterForm.assignee_id" :class="selectClass">
                                    <option value="">Anyone</option>
                                    <option value="unassigned">Unassigned</option>
                                    <option v-for="person in filter_assignees" :key="person.id" :value="String(person.id)">{{ person.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div class="grid gap-1.5">
                                <Label for="task-filter-sort">Sort by</Label>
                                <select id="task-filter-sort" v-model="filterForm.sort" :class="selectClass" class="min-w-36">
                                    <option value="newest">Newest first</option>
                                    <option value="oldest">Oldest first</option>
                                    <option value="priority">Highest priority</option>
                                    <option value="due_date">Due date</option>
                                    <option value="title">Title A–Z</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link :href="indexUrl" class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:text-foreground">Clear filters</Link>
                                <Button type="submit" size="sm"><Search class="size-4" /> Apply</Button>
                            </div>
                        </div>
                    </form>
                </Card>

                <Card v-if="tasks.length" class="gap-0 overflow-hidden py-0">
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
                                        <Link :href="taskUrl(task.id)" class="flex items-start gap-2 hover:underline">
                                            <CheckCircle2 v-if="task.status.category === 'done'" class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <Circle v-else class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <span class="min-w-0">
                                                <span class="block font-medium">{{ task.title }}</span>
                                                <span class="block text-xs text-muted-foreground">
                                                    {{ task.reference }} · {{ task.type_label }}
                                                    <template v-if="task.parent"> · Subtask of {{ task.parent.reference }}</template>
                                                </span>
                                            </span>
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3"><Badge :variant="badgeVariant(task.status.category)">{{ task.status.name }}</Badge></td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ task.priority_label }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ task.assignee?.name ?? 'Unassigned' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
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
                    <Form :action="`/projects/${project.id}/tasks`" method="post" class="space-y-4" reset-on-success v-slot="{ errors, processing }">
                        <div class="grid gap-2">
                            <Label for="task-title">Title</Label>
                            <Input id="task-title" name="title" maxlength="200" required placeholder="Implement dashboard" />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-description">Description</Label>
                            <textarea id="task-description" name="description" rows="3" maxlength="10000" placeholder="Optional details" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="task-type">Type</Label>
                                <select id="task-type" name="type" :class="selectClass" required>
                                    <option value="1">Task</option>
                                    <option value="2">Feature</option>
                                    <option value="3">Improvement</option>
                                    <option value="4">Bug</option>
                                </select>
                                <InputError :message="errors.type" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="task-priority">Priority</Label>
                                <select id="task-priority" name="priority" :class="selectClass" required>
                                    <option value="2">Normal</option>
                                    <option value="1">Low</option>
                                    <option value="3">High</option>
                                    <option value="4">Urgent</option>
                                </select>
                                <InputError :message="errors.priority" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-status">Status</Label>
                            <select id="task-status" name="status_id" :class="selectClass">
                                <option value="">Default (Todo)</option>
                                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                            </select>
                            <InputError :message="errors.status_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-assignee">Assignee</Label>
                            <select id="task-assignee" name="assignee_id" :class="selectClass">
                                <option value="">Unassigned</option>
                                <option v-for="person in assignees" :key="person.id" :value="person.id">{{ person.name }}</option>
                            </select>
                            <InputError :message="errors.assignee_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-parent">Parent task</Label>
                            <select id="task-parent" name="parent_id" :class="selectClass">
                                <option value="">None (regular task)</option>
                                <option v-for="task in parent_tasks" :key="task.id" :value="task.id">{{ task.reference }} — {{ task.title }}</option>
                            </select>
                            <InputError :message="errors.parent_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="task-due-date">Due date</Label>
                            <Input id="task-due-date" name="due_date" type="date" />
                            <InputError :message="errors.due_date" />
                        </div>
                        <Button :disabled="processing" class="w-full"><Plus class="size-4" /> Create task</Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
