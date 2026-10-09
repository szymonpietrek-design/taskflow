<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, LoaderCircle, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import TaskDiscussion from './Discussion.vue';
import TaskDetailsForm from './TaskDetailsForm.vue';
import TaskSubtasks from './TaskSubtasks.vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { TaskDetail, TaskMessageSummary, TaskProjectSummary, TaskStatusSummary, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    project: TaskProjectSummary;
    task: TaskDetail;
    statuses: TaskStatusSummary[];
    can_update: boolean;
    can_create_subtask: boolean;
    assignees: TaskUserSummary[];
    can_post_message: boolean;
    messages: TaskMessageSummary[];
}>();

const editingDetails = ref(false);
// The same Inertia page may be reused when a new subtask is opened.
watch(() => props.task.id, () => { editingDetails.value = false; });
const statusUrl = computed(() => `/projects/${props.project.id}/tasks/${props.task.id}/status`);
const selectedStatus = ref(String(props.task.status.id));
watch(() => props.task.status.id, (id) => { selectedStatus.value = String(id); });
const hasStatusChange = computed(() => selectedStatus.value !== String(props.task.status.id));
const stayOnTask = { preserveScroll: true, preserveState: true };
const notifyError = () => toast.error('Could not update the status. Check the selection and try again.');

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
            <div class="mt-3 flex items-start justify-between gap-4">
                <h1 class="text-2xl font-semibold tracking-tight">{{ task.title }}</h1>
                <Button v-if="can_update && !editingDetails" type="button" size="sm" variant="outline" @click="editingDetails = true">
                    <Pencil class="size-4" /> Edit task
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <div class="space-y-5">
                <TaskDetailsForm v-if="editingDetails && can_update" :project-id="project.id" :task="task" :assignees="assignees"
                    @saved="editingDetails = false" @cancel="editingDetails = false" />
                <Card v-else>
                    <CardHeader>
                        <CardTitle>Description</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p v-if="task.description" class="whitespace-pre-wrap text-sm leading-relaxed">{{ task.description }}</p>
                        <p v-else class="text-sm text-muted-foreground">No description yet.</p>
                        <p v-if="task.source_task" class="mt-4 text-xs text-muted-foreground">
                            Created from a message in
                            <Link :href="`/projects/${project.id}/tasks/${task.source_task.id}${task.source_task.message_id ? '#message-' + task.source_task.message_id : ''}`" class="underline">
                                {{ task.source_task.reference }}
                            </Link>
                            <span v-if="!task.source_task.message_id"> (source message removed)</span>
                        </p>
                        <p v-if="task.parent" class="mt-4 text-xs text-muted-foreground">
                            Subtask of
                            <Link :href="`/projects/${project.id}/tasks/${task.parent.id}`" class="underline">{{ task.parent.reference }} — {{ task.parent.title }}</Link>
                        </p>
                    </CardContent>
                </Card>

                <TaskSubtasks
                    v-if="task.parent === null"
                    :project-id="project.id"
                    :parent-task-id="task.id"
                    :subtasks="task.subtasks"
                    :progress="task.subtask_progress"
                    :statuses="statuses"
                    :assignees="assignees"
                    :can-create="can_create_subtask"
                />

                <TaskDiscussion
                    :project-id="project.id"
                    :task-id="task.id"
                    :messages="messages"
                    :can-post="can_post_message"
                />
            </div>

            <Card>
                <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <div>
                        <p class="text-xs text-muted-foreground">Status</p>
                        <Form v-if="can_update" :action="statusUrl" method="patch" :options="stayOnTask" @error="notifyError"
                            class="mt-2 space-y-2" v-slot="{ errors, processing }">
                            <input type="hidden" name="status_id" :value="selectedStatus" />
                            <Select v-model="selectedStatus" :disabled="processing">
                                <SelectTrigger aria-label="Task status" class="w-full"><SelectValue placeholder="Select status" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="status in statuses" :key="status.id" :value="String(status.id)">{{ status.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.status_id" />
                            <Button type="submit" variant="secondary" size="sm" :disabled="processing || !hasStatusChange">
                                <LoaderCircle v-if="processing" class="size-4 animate-spin" />{{ processing ? 'Updating…' : 'Update status' }}
                            </Button>
                        </Form>
                        <Badge v-else class="mt-2" variant="secondary">{{ task.status.name }}</Badge>
                    </div>
                    <div v-if="task.release">
                        <Label class="text-xs text-muted-foreground">Release</Label>
                        <p class="mt-1"><Link :href="`/projects/${project.id}/releases/${task.release.id}`" class="underline">v{{ task.release.version }}</Link></p>
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
