<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Circle, ClipboardList, Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TaskProjectSummary, TaskStatusSummary, TaskSummary, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    project: TaskProjectSummary;
    tasks: TaskSummary[];
    statuses: TaskStatusSummary[];
    assignees: TaskUserSummary[];
    can_create: boolean;
}>();

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
                    <span class="text-xs text-muted-foreground">{{ tasks.length }} {{ tasks.length === 1 ? 'task' : 'tasks' }}</span>
                </div>

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
                        <p class="font-medium">No tasks yet</p>
                        <p class="max-w-sm text-sm text-muted-foreground">{{ can_create ? 'Create the first task to start tracking work.' : 'Tasks will appear here when project members add them.' }}</p>
                    </CardContent>
                </Card>
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
                                <option v-for="task in tasks.filter((item) => !item.parent)" :key="task.id" :value="task.id">{{ task.reference }} — {{ task.title }}</option>
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
