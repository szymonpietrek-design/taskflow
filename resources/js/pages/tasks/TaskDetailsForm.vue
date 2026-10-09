<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { TaskDetail, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    task: TaskDetail;
    assignees: TaskUserSummary[];
}>();

const emit = defineEmits<{ saved: []; cancel: [] }>();
const updateUrl = `/projects/${props.projectId}/tasks/${props.task.id}`;
const priorities = [
    { value: 1, label: 'Low' },
    { value: 2, label: 'Normal' },
    { value: 3, label: 'High' },
    { value: 4, label: 'Urgent' },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Edit task</CardTitle>
            <CardDescription>Update the task details. Only changed fields are recorded in the activity history.</CardDescription>
        </CardHeader>
        <CardContent>
            <Form :action="updateUrl" method="patch" class="space-y-4" @success="emit('saved')" v-slot="{ errors, processing }">
                <div class="space-y-2">
                    <Label for="task-edit-title">Title</Label>
                    <input id="task-edit-title" name="title" type="text" required maxlength="200" :value="task.title"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <InputError :message="errors.title" />
                </div>
                <div class="space-y-2">
                    <Label for="task-edit-description">Description</Label>
                    <textarea id="task-edit-description" name="description" rows="5" maxlength="10000" :value="task.description ?? ''"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="task-edit-priority">Priority</Label>
                        <select id="task-edit-priority" name="priority" required :value="task.priority"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs">
                            <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
                        </select>
                        <InputError :message="errors.priority" />
                    </div>
                    <div class="space-y-2">
                        <Label for="task-edit-assignee">Assigned to</Label>
                        <select id="task-edit-assignee" name="assignee_id" :value="task.assignee?.id ?? ''"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs">
                            <option value="">Unassigned</option>
                            <option v-if="task.assignee && !assignees.some(user => user.id === task.assignee?.id)" :value="task.assignee.id">
                                {{ task.assignee.name }} (current)
                            </option>
                            <option v-for="user in assignees" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <InputError :message="errors.assignee_id" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="task-edit-date">Due date</Label>
                    <input id="task-edit-date" name="due_date" type="date" :value="task.due_date ?? ''"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs" />
                    <InputError :message="errors.due_date" />
                </div>
                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="processing">{{ processing ? 'Saving…' : 'Save changes' }}</Button>
                    <Button type="button" variant="outline" :disabled="processing" @click="emit('cancel')">Cancel</Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
