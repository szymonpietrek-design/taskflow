<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, MessageSquare } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import type { TaskDetail, TaskProjectSummary, TaskStatusSummary } from '@/types/task';

const props = defineProps<{
    project: TaskProjectSummary;
    task: TaskDetail;
    statuses: TaskStatusSummary[];
    can_update: boolean;
}>();

const statusUrl = `/projects/${props.project.id}/tasks/${props.task.id}/status`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});
</script>

<template>
    <Head :title="`${task.reference} · ${task.title}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <Link :href="`/projects/${project.id}/tasks`" class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ project.name }} / Tasks
            </Link>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ task.reference }}</Badge>
                <Badge variant="outline">{{ task.type_label }}</Badge>
                <Badge variant="outline">{{ task.priority_label }} priority</Badge>
            </div>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight">{{ task.title }}</h1>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <div class="space-y-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Description</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p v-if="task.description" class="whitespace-pre-wrap text-sm leading-relaxed">{{ task.description }}</p>
                        <p v-else class="text-sm text-muted-foreground">No description yet.</p>
                        <p v-if="task.parent" class="mt-4 text-xs text-muted-foreground">
                            Subtask of
                            <Link :href="`/projects/${project.id}/tasks/${task.parent.id}`" class="underline">{{ task.parent.reference }} — {{ task.parent.title }}</Link>
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="task.subtasks.length">
                    <CardHeader><CardTitle>Subtasks</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <Link v-for="child in task.subtasks" :key="child.id" :href="`/projects/${project.id}/tasks/${child.id}`" class="flex items-center justify-between gap-3 text-sm hover:underline">
                            <span><span class="text-muted-foreground">{{ child.reference }}</span> {{ child.title }}</span>
                            <Badge variant="secondary">{{ child.status }}</Badge>
                        </Link>
                    </CardContent>
                </Card>

                <Card class="border-dashed">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2"><MessageSquare class="size-4" /> Discussion</CardTitle>
                        <CardDescription>Task messages will be available in Stage 3D.2.</CardDescription>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <div>
                        <p class="text-xs text-muted-foreground">Status</p>
                        <Form v-if="can_update" :action="statusUrl" method="patch" class="mt-2 space-y-2" v-slot="{ errors, processing }">
                            <select name="status_id" :value="task.status.id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs">
                                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                            </select>
                            <InputError :message="errors.status_id" />
                            <Button variant="secondary" size="sm" :disabled="processing">Update status</Button>
                        </Form>
                        <Badge v-else class="mt-2" variant="secondary">{{ task.status.name }}</Badge>
                    </div>
                    <div>
                        <Label class="text-xs text-muted-foreground">Assigned to</Label>
                        <p class="mt-1">{{ task.assignee?.name ?? 'Unassigned' }}</p>
                    </div>
                    <div>
                        <Label class="text-xs text-muted-foreground">Created by</Label>
                        <p class="mt-1">{{ task.creator.name }}</p>
                    </div>
                    <div>
                        <Label class="text-xs text-muted-foreground">Due date</Label>
                        <p class="mt-1 inline-flex items-center gap-1"><CalendarDays class="size-4 text-muted-foreground" /> {{ task.due_date ?? 'Not set' }}</p>
                    </div>
                    <div v-if="task.completed_at">
                        <p class="text-xs text-muted-foreground">Completed</p>
                        <p class="mt-1">{{ new Date(task.completed_at).toLocaleString() }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
