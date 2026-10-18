<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, LoaderCircle, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import TaskMarkdown from '@/components/tasks/TaskMarkdown.vue';
import TaskAttachments from '@/components/tasks/TaskAttachments.vue';
import TaskImagePreviewDialog from '@/components/tasks/TaskImagePreviewDialog.vue';
import TaskStatusIcon from '@/components/tasks/TaskStatusIcon.vue';
import TaskTypeLabel from '@/components/tasks/TaskTypeLabel.vue';
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
const editingStatus = ref(false);
const selectedStatus = ref(String(props.task.status.id));
const inlineImagePreview = ref<TaskAttachmentSummary | null>(null);
// Only existing, authorized task attachments can be opened in the lightbox.
function previewEmbeddedImage(url: string): void {
    const match = url.match(/\/attachments\/(\d+)\/preview(?:\?.*)?$/);
    if (!match) return;
    inlineImagePreview.value = props.attachments.find(
        attachment => attachment.id === Number(match[1]) && attachment.is_image,
    ) ?? null;
}
// Links and images already resolved by Laravel's guarded Markdown renderer.
// Use the rendered URLs so references inside code fences are not mistaken for embeddings.
const embeddedAttachmentIds = computed(() => {
    const ids = new Set<number>();
    for (const match of (props.task.description_html ?? '').matchAll(/\/attachments\/(\d+)\/(?:preview|download)\b/g)) {
        ids.add(Number(match[1]));
    }
    return [...ids];
});
// The same Inertia page may be reused when a new subtask is opened.
watch(
    () => props.task.id,
    () => {
        editingDetails.value = false;
        editingStatus.value = false;
        selectedStatus.value = String(props.task.status.id);
        inlineImagePreview.value = null;
    },
);
const statusUrl = computed(
    () => `/projects/${props.project.id}/tasks/${props.task.id}/status`,
);
watch(
    () => props.task.status.id,
    (id) => {
        selectedStatus.value = String(id);
    },
);
const hasStatusChange = computed(
    () => selectedStatus.value !== String(props.task.status.id),
);
function startStatusEdit(): void {
    selectedStatus.value = String(props.task.status.id);
    editingStatus.value = true;
}
function finishStatusEdit(): void {
    editingStatus.value = false;
    selectedStatus.value = String(props.task.status.id);
}
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
                <TaskTypeLabel :type="task.type" variant="badge" />
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
                    :attachments="attachments"
                    :assignees="assignees"
                    @saved="editingDetails = false"
                    @cancel="editingDetails = false"
                />
                <Card v-else>
                    <CardHeader>
                        <CardTitle>{{ t('stage2.description') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TaskMarkdown
                            v-if="task.description_html"
                            :html="task.description_html"
                            zoom-images
                            @image-click="previewEmbeddedImage"
                        />
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
                    :embedded-attachment-ids="embeddedAttachmentIds"
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
                    :available-attachments="attachments"
                    :can-post="can_post_message"
                />
            </div>

            <Card class="lg:sticky lg:top-6">
                <CardHeader class="pb-3">
                    <CardTitle>{{ t('stage2.details') }}</CardTitle>
                </CardHeader>
                <CardContent class="text-sm">
                    <div class="space-y-4">
                        <section>
                            <p class="mb-2 text-xs font-medium text-muted-foreground">{{ t('stage2.status') }}</p>
                            <div class="flex min-h-8 flex-wrap items-center gap-2">
                                <Badge variant="outline" class="gap-1.5" :class="taskStatusBadgeClass(task.status.category)">
                                    <TaskStatusIcon :category="task.status.category" />
                                    {{ task.status.name }}
                                </Badge>
                                <Button
                                    v-if="can_update && !editingStatus"
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-7 text-muted-foreground hover:text-foreground"
                                    :aria-label="t('stage2.edit_status')"
                                    :title="t('stage2.edit_status')"
                                    :aria-expanded="editingStatus"
                                    aria-controls="task-status-editor"
                                    @click="startStatusEdit"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                            </div>
                            <Form
                                v-if="can_update && editingStatus"
                                id="task-status-editor"
                                :action="statusUrl"
                                method="patch"
                                :options="stayOnTask"
                                @error="notifyError"
                                @success="finishStatusEdit"
                                class="mt-3 space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3"
                                v-slot="{ errors, processing }"
                            >
                                <input type="hidden" name="status_id" :value="selectedStatus" />
                                <Select v-model="selectedStatus" :disabled="processing">
                                    <SelectTrigger :aria-label="t('stage2.task_status')" class="w-full">
                                        <SelectValue :placeholder="t('stage2.select_status')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="status in statuses"
                                            :key="status.id"
                                            :value="String(status.id)"
                                        >{{ status.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.status_id" />
                                <div class="flex items-center gap-2">
                                    <Button type="submit" size="sm" :disabled="processing || !hasStatusChange">
                                        <LoaderCircle v-if="processing" class="size-4 animate-spin" />
                                        {{ processing ? t('followup.updating') : t('common.save') }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="processing"
                                        @click="finishStatusEdit"
                                    >{{ t('common.cancel') }}</Button>
                                </div>
                            </Form>
                        </section>

                        <section v-if="task.release" class="border-t border-border/60 pt-4">
                            <Label class="text-xs text-muted-foreground">{{ t('stage2.release') }}</Label>
                            <p class="mt-1 font-medium">
                                <Link
                                    :href="`/projects/${project.id}/releases/${task.release.id}`"
                                    class="underline decoration-muted-foreground/40 underline-offset-4 hover:text-primary"
                                >v{{ task.release.version }}</Link>
                            </p>
                        </section>

                        <section class="border-t border-border/60 pt-4">
                            <Label class="text-xs text-muted-foreground">{{ t('stage2.assigned_to') }}</Label>
                            <div v-if="task.assignee" class="mt-2 flex min-w-0 items-center gap-2.5">
                                <UserAvatar :user-id="task.assignee.id" :name="task.assignee.name" small />
                                <span class="min-w-0 break-words font-medium">{{ task.assignee.name }}</span>
                            </div>
                            <p v-else class="mt-1 text-muted-foreground">{{ t('fix.no_assignee') }}</p>
                        </section>

                        <section class="border-t border-border/60 pt-4">
                            <Label class="text-xs text-muted-foreground">{{ t('stage2.created_by') }}</Label>
                            <div class="mt-2 flex min-w-0 items-center gap-2.5">
                                <UserAvatar :user-id="task.creator.id" :name="task.creator.name" small />
                                <span class="min-w-0 break-words font-medium">{{ task.creator.name }}</span>
                            </div>
                        </section>

                        <section class="border-t border-border/60 pt-4">
                            <Label class="text-xs text-muted-foreground">{{ t('stage2.due_date') }}</Label>
                            <p class="mt-1.5 inline-flex items-center gap-2">
                                <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
                                <span>{{ dueDateLabel }}</span>
                            </p>
                        </section>
                        <section v-if="task.completed_at" class="border-t border-border/60 pt-4">
                            <p class="text-xs text-muted-foreground">{{ t('stage2.completed') }}</p>
                            <p class="mt-1">
                                {{ new Date(task.completed_at).toLocaleString(locale === 'pl' ? 'pl-PL' : 'en-US') }}
                            </p>
                        </section>
                    </div>
                </CardContent>
            </Card>
        </div>
        <TaskImagePreviewDialog
            :project-id="project.id"
            :task-id="task.id"
            :file="inlineImagePreview"
            @close="inlineImagePreview = null"
        />
    </div>
</template>
