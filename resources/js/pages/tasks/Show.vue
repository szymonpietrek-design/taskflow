<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, LoaderCircle, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import TaskMarkdown from '@/components/tasks/TaskMarkdown.vue';
import TaskAttachments from '@/components/tasks/TaskAttachments.vue';
import TaskStatusIcon from '@/components/tasks/TaskStatusIcon.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { taskStatusBadgeClass, taskPriorityDotClass } from '@/lib/taskVisuals';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import TaskDiscussion from './Discussion.vue';
import TaskDetailsForm from './TaskDetailsForm.vue';
import TaskSubtasks from './TaskSubtasks.vue';
import TaskWorklog from './Worklog.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    TaskDetail,
    TaskAttachmentSummary,
    TaskMessageSummary,
    TaskProjectSummary,
    TaskStatusSummary,
    TaskUserSummary,
    TaskWorklogPage,
} from '@/types/task';

const props = defineProps<{
    project: TaskProjectSummary;
    task: TaskDetail;
    attachments: TaskAttachmentSummary[];
    statuses: TaskStatusSummary[];
    can_update: boolean;
    can_create_subtask: boolean;
    assignees: TaskUserSummary[];
    can_post_message: boolean;
    messages: TaskMessageSummary[];
    worklogs: TaskWorklogPage;
    worklog_total_minutes: number;
    can_log_work: boolean;
}>();

const editingDetails = ref(false);
// The same Inertia page may be reused when a new subtask is opened.
watch(
    () => props.task.id,
    () => {
        editingDetails.value = false;
    },
);
const statusUrl = computed(
    () => `/projects/${props.project.id}/tasks/${props.task.id}/status`,
);
const selectedStatus = ref(String(props.task.status.id));
watch(
    () => props.task.status.id,
    (id) => {
        selectedStatus.value = String(id);
    },
);
const hasStatusChange = computed(
    () => selectedStatus.value !== String(props.task.status.id),
);
const stayOnTask = { preserveScroll: true, preserveState: true };
const dueDateLabel = computed(() => {
    if (!props.task.due_date) return t('fix.not_set');
    // Calendar-only dates must not be shifted by the browser's timezone.
    const date = new Date(`${props.task.due_date.slice(0, 10)}T12:00:00Z`);
    return new Intl.DateTimeFormat(locale.value === 'pl' ? 'pl-PL' : 'en-US', {
        dateStyle: 'medium', timeZone: 'UTC',
    }).format(date);
});
const notifyError = () =>
    toast.error(
        t('fix.no_time_change'),
    );

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});
const { t, displayLabel, locale } = useI18n();
</script>

<template>
    <Head :title="`${task.reference} · ${task.title}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <Link
                :href="`/projects/${project.id}/tasks`"
                class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> {{ project.name }} / {{ t('followup.tasks') }}
            </Link>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ task.reference }}</Badge>
                <Badge variant="outline">{{ displayLabel(task.type_label) }}</Badge>
                <Badge variant="outline" class="gap-1.5">
                    <span class="size-2 rounded-full" :class="taskPriorityDotClass(task.priority)" />
                    {{ t('followup.priority') }}: {{ displayLabel(task.priority_label) }}
                </Badge>
            </div>
            <div class="mt-3 flex items-start justify-between gap-4">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ task.title }}
                </h1>
                <Button
                    v-if="can_update && !editingDetails"
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="editingDetails = true"
                >
                    <Pencil class="size-4" /> {{ t('stage2.edit_task') }}
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <div class="space-y-5">
                <TaskDetailsForm
                    v-if="editingDetails && can_update"
                    :project-id="project.id"
                    :task="task"
                    :assignees="assignees"
                    @saved="editingDetails = false"
                    @cancel="editingDetails = false"
                />
                <Card v-else>
                    <CardHeader>
                        <CardTitle>{{ t('stage2.description') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TaskMarkdown v-if="task.description_html" :html="task.description_html" />
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('stage2.no_description_yet') }}
                        </p>
                        <p
                            v-if="task.source_task"
                            class="mt-4 text-xs text-muted-foreground"
                        >
                            {{ t('stage2.created_from_a_message_in') }}
                            <Link
                                :href="`/projects/${project.id}/tasks/${task.source_task.id}${task.source_task.message_id ? '#message-' + task.source_task.message_id : ''}`"
                                class="underline"
                            >
                                {{ task.source_task.reference }}
                            </Link>
                            <span v-if="!task.source_task.message_id">
                                {{ t('stage2.source_message_removed') }}</span
                            >
                        </p>
                        <p
                            v-if="task.parent"
                            class="mt-4 text-xs text-muted-foreground"
                        >
                            {{ t('stage2.subtask_of') }}
                            <Link
                                :href="`/projects/${project.id}/tasks/${task.parent.id}`"
                                class="underline"
                                >{{ task.parent.reference }} —
                                {{ task.parent.title }}</Link
                            >
                        </p>
                    </CardContent>
                </Card>

                <TaskAttachments
                    :project-id="project.id"
                    :task-id="task.id"
                    :attachments="attachments"
                    :can-update="can_update"
                />

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

                <TaskWorklog
                    :project-id="project.id"
                    :task-id="task.id"
                    :entries="worklogs"
                    :total-minutes="worklog_total_minutes"
                    :can-log="can_log_work"
                />

                <TaskDiscussion
                    :project-id="project.id"
                    :task-id="task.id"
                    :messages="messages"
                    :can-post="can_post_message"
                />
            </div>

            <Card>
                <CardHeader><CardTitle>{{ t('stage2.details') }}</CardTitle></CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <div>
                        <p class="text-xs text-muted-foreground">{{ t('stage2.status') }}</p>
                        <Badge variant="outline" class="mt-2 gap-1.5" :class="taskStatusBadgeClass(task.status.category)">
                            <TaskStatusIcon :category="task.status.category" />
                            {{ task.status.name }}
                        </Badge>
                        <Form
                            v-if="can_update"
                            :action="statusUrl"
                            method="patch"
                            :options="stayOnTask"
                            @error="notifyError"
                            class="mt-2 space-y-2"
                            v-slot="{ errors, processing }"
                        >
                            <input
                                type="hidden"
                                name="status_id"
                                :value="selectedStatus"
                            />
                            <Select
                                v-model="selectedStatus"
                                :disabled="processing"
                            >
                                <SelectTrigger
                                    :aria-label="t('stage2.task_status')"
                                    class="w-full"
                                    ><SelectValue :placeholder="t('stage2.select_status')"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="status in statuses"
                                        :key="status.id"
                                        :value="String(status.id)"
                                        >{{ status.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.status_id" />
                            <Button
                                type="submit"
                                variant="secondary"
                                size="sm"
                                :disabled="processing || !hasStatusChange"
                            >
                                <LoaderCircle
                                    v-if="processing"
                                    class="size-4 animate-spin"
                                />{{
                                    processing ? t('followup.updating') : t('followup.update_status')
                                }}
                            </Button>
                        </Form>
                    </div>
                    <div v-if="task.release">
                        <Label class="text-xs text-muted-foreground"
                            >{{ t('stage2.release') }}</Label
                        >
                        <p class="mt-1">
                            <Link
                                :href="`/projects/${project.id}/releases/${task.release.id}`"
                                class="underline"
                                >v{{ task.release.version }}</Link
                            >
                        </p>
                    </div>
                    <div>
                        <Label class="text-xs text-muted-foreground"
                            >{{ t('stage2.assigned_to') }}</Label
                        >
                        <div v-if="task.assignee" class="mt-1 flex items-center gap-2">
                            <UserAvatar :user-id="task.assignee.id" :name="task.assignee.name" small />
                            <span>{{ task.assignee.name }}</span>
                        </div>
                        <p v-else class="mt-1">{{ t('fix.no_assignee') }}</p>
                    </div>
                    <div>
                        <Label class="text-xs text-muted-foreground"
                            >{{ t('stage2.created_by') }}</Label
                        >
                        <div class="mt-1 flex items-center gap-2">
                            <UserAvatar :user-id="task.creator.id" :name="task.creator.name" small />
                            <span>{{ task.creator.name }}</span>
                        </div>
                    </div>
                    <div>
                        <Label class="text-xs text-muted-foreground"
                            >{{ t('stage2.due_date') }}</Label
                        >
                        <p class="mt-1 inline-flex items-center gap-1">
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                            />
                            {{ dueDateLabel }}
                        </p>
                    </div>
                    <div v-if="task.completed_at">
                        <p class="text-xs text-muted-foreground">{{ t('stage2.completed') }}</p>
                        <p class="mt-1">
                            {{ new Date(task.completed_at).toLocaleString(locale === 'pl' ? 'pl-PL' : 'en-US') }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
