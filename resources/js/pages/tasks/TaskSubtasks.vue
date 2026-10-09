<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { CheckCircle2, Circle, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TaskStatusSummary, TaskSubtaskSummary, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    parentTaskId: number;
    subtasks: TaskSubtaskSummary[];
    progress: { total: number; done: number };
    statuses: TaskStatusSummary[];
    assignees: TaskUserSummary[];
    canCreate: boolean;
}>();

const creating = ref(false);
const percentage = computed(() => props.progress.total === 0
    ? 0
    : Math.round((props.progress.done / props.progress.total) * 100));

function taskUrl(id: number): string {
    return `/projects/${props.projectId}/tasks/${id}`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle>Subtasks</CardTitle>
                    <CardDescription v-if="progress.total">
                        {{ progress.done }} of {{ progress.total }} completed ({{ percentage }}%)
                    </CardDescription>
                    <CardDescription v-else>Split this task into smaller steps.</CardDescription>
                </div>
                <Button v-if="canCreate && !creating" type="button" size="sm" variant="outline" @click="creating = true">
                    <Plus class="size-4" /> Add subtask
                </Button>
            </div>
            <div v-if="progress.total" class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted" role="progressbar"
                :aria-valuenow="percentage" aria-valuemin="0" aria-valuemax="100" aria-label="Subtask completion">
                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${percentage}%` }" />
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <ul v-if="subtasks.length" class="divide-y">
                <li v-for="child in subtasks" :key="child.id" class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <Link :href="taskUrl(child.id)" class="flex min-w-0 flex-1 items-start gap-2 text-sm hover:underline">
                        <CheckCircle2 v-if="child.status.category === 'done'" class="mt-0.5 size-4 shrink-0 text-primary" />
                        <Circle v-else class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span class="min-w-0">
                            <span class="block font-medium break-words">{{ child.title }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ child.reference }}
                                <template v-if="child.assignee"> · {{ child.assignee.name }}</template>
                                <template v-if="child.due_date"> · Due {{ child.due_date }}</template>
                            </span>
                        </span>
                    </Link>
                    <Badge :variant="child.status.category === 'done' ? 'default' : 'secondary'">{{ child.status.name }}</Badge>
                </li>
            </ul>
            <p v-else-if="!creating" class="text-sm text-muted-foreground">No subtasks yet.</p>

            <Form v-if="canCreate && creating" :action="`/projects/${projectId}/tasks`" method="post"
                class="space-y-3 rounded-md border bg-muted/20 p-4" reset-on-success v-slot="{ errors, processing }">
                <input type="hidden" name="parent_id" :value="parentTaskId" />
                <input type="hidden" name="type" value="1" />
                <div class="grid gap-1.5">
                    <Label for="subtask-title">Subtask title</Label>
                    <Input id="subtask-title" name="title" maxlength="200" required placeholder="Write tests" />
                    <InputError :message="errors.title || errors.parent_id" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="subtask-priority">Priority</Label>
                        <select id="subtask-priority" name="priority" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="2">Normal</option>
                            <option value="1">Low</option>
                            <option value="3">High</option>
                            <option value="4">Urgent</option>
                        </select>
                        <InputError :message="errors.priority" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="subtask-status">Status</Label>
                        <select id="subtask-status" name="status_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Default (Todo)</option>
                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                        </select>
                        <InputError :message="errors.status_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="subtask-assignee">Assignee</Label>
                        <select id="subtask-assignee" name="assignee_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Unassigned</option>
                            <option v-for="person in assignees" :key="person.id" :value="person.id">{{ person.name }}</option>
                        </select>
                        <InputError :message="errors.assignee_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="subtask-due-date">Due date</Label>
                        <Input id="subtask-due-date" name="due_date" type="date" />
                        <InputError :message="errors.due_date" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button size="sm" :disabled="processing"><Plus class="size-4" /> Create subtask</Button>
                    <Button type="button" size="sm" variant="outline" :disabled="processing" @click="creating = false">Cancel</Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
