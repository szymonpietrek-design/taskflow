<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, router } from '@inertiajs/vue3';
import type { DateValue } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import { CalendarDays, LoaderCircle, Paperclip, ChevronDown } from '@lucide/vue';
import { computed, ref, shallowRef } from 'vue';
import { AsyncVisualMarkdownEditor } from '@/components/tasks/AsyncVisualMarkdownEditor';
import type VisualMarkdownEditorComponent from '@/components/tasks/VisualMarkdownEditor.vue';
import TaskAttachmentLibraryPicker from '@/components/tasks/TaskAttachmentLibraryPicker.vue';
import TaskTypeLabel from '@/components/tasks/TaskTypeLabel.vue';
import TaskPrioritySelect from '@/components/tasks/TaskPrioritySelect.vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TaskAttachmentSummary, TaskDetail, TaskStatusSummary, TaskTypeValue, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    task: TaskDetail;
    assignees: TaskUserSummary[];
    statuses: TaskStatusSummary[];
    attachments: TaskAttachmentSummary[];
}>();

const emit = defineEmits<{ saved: []; cancel: [] }>();
const updateUrl = computed(
    () => `/projects/${props.projectId}/tasks/${props.task.id}`,
);
// Controlled shadcn-vue fields need hidden inputs for Inertia's Form payload.
const editType = ref(String(props.task.type));
const selectedType = computed(() => Number(editType.value) as TaskTypeValue);
const editPriority = ref(String(props.task.priority));
const editStatus = ref(String(props.task.status.id));
const editDescription = ref(props.task.description ?? '');
const descriptionEditor = ref<InstanceType<typeof VisualMarkdownEditorComponent> | null>(null);
const descriptionLibraryOpen = ref(false);

// Attachment actions also work while the rich editor chunk is still loading.
function insertDescriptionAttachment(file: TaskAttachmentSummary, image: boolean): void {
    if (descriptionEditor.value) {
        descriptionEditor.value.insertAttachment(file, image);
        return;
    }
    const safeName = file.name.replace(/[\r\n]/g, ' ').replace(/[\\\[\]]/g, '\\$&');
    const current = editDescription.value;
    editDescription.value = `${current}${current && !current.endsWith('\n') ? '\n' : ''}${image ? '!' : ''}[${safeName}](attachment:${file.id})\n`;
}

const editAssignee = ref(
    props.task.assignee ? String(props.task.assignee.id) : 'unassigned',
);
const editDueDate = shallowRef<DateValue | undefined>(
    props.task.due_date
        ? parseDate(props.task.due_date.slice(0, 10))
        : undefined,
);
const dateFormatter = computed(() => new DateFormatter(locale.value === 'pl' ? 'pl-PL' : 'en-US', { dateStyle: 'medium' }));
const notifyError = () =>
    toast.error(t('fix.could_not_update_task'));
const stayOnTask = { preserveScroll: true, preserveState: true };

const { t, locale } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('stage2.edit_task') }}</CardTitle>
            <CardDescription
                >{{ t('audit.update_the_task_details_only_changed_fields_are_recorded_in_the_activity_history') }}</CardDescription
            >
        </CardHeader>
        <CardContent>
            <Form
                :action="updateUrl"
                method="patch"
                :options="stayOnTask"
                class="space-y-4"
                @success="emit('saved')"
                @error="notifyError"
                v-slot="{ errors, processing }"
            >
                <div class="space-y-2">
                    <Label for="task-edit-title">{{ t('stage2.title') }}</Label>
                    <Input
                        id="task-edit-title"
                        name="title"
                        type="text"
                        required
                        maxlength="200"
                        :aria-invalid="!!errors.title || undefined"
                        :default-value="task.title"
                        :disabled="processing"
                    />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="task-edit-type">{{ t('stage2.type') }}</Label>
                        <input type="hidden" name="type" :value="editType" />
                        <Select v-model="editType" :disabled="processing">
                            <SelectTrigger id="task-edit-type" class="w-full" :aria-invalid="!!errors.type || undefined">
                                <span class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                    <TaskTypeLabel :type="selectedType" icon-only />
                                    <SelectValue class="min-w-0 flex-1 text-left" :placeholder="t('stage2.select_type')" />
                                </span>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1"><TaskTypeLabel :type="1" /></SelectItem>
                                <SelectItem value="2"><TaskTypeLabel :type="2" /></SelectItem>
                                <SelectItem value="3"><TaskTypeLabel :type="3" /></SelectItem>
                                <SelectItem value="4"><TaskTypeLabel :type="4" /></SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.type" />
                    </div>
                    <div class="space-y-2">
                        <Label for="task-edit-priority">{{ t('stage2.priority') }}</Label>
                        <input
                            type="hidden"
                            name="priority"
                            :value="editPriority"
                        />
                        <TaskPrioritySelect
                            id="task-edit-priority"
                            v-model="editPriority"
                            :disabled="processing"
                            :invalid="!!errors.priority"
                        />
                        <InputError :message="errors.priority" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="task-edit-status">{{ t('stage2.status') }}</Label>
                        <input type="hidden" name="status_id" :value="editStatus" />
                        <Select v-model="editStatus" :disabled="processing">
                            <SelectTrigger
                                id="task-edit-status"
                                class="w-full"
                                :aria-invalid="!!errors.status_id || undefined"
                            >
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
                    </div>
                    <div class="space-y-2">
                        <Label for="task-edit-assignee"
                            >{{ t('stage2.assigned_to') }}
                            <span class="font-normal text-muted-foreground"
                                >{{ t('stage2.optional') }}</span
                            ></Label
                        >
                        <input
                            type="hidden"
                            name="assignee_id"
                            :value="
                                editAssignee === 'unassigned'
                                    ? ''
                                    : editAssignee
                            "
                        />
                        <Select v-model="editAssignee" :disabled="processing">
                            <SelectTrigger
                                id="task-edit-assignee"
                                class="w-full"
                                :aria-invalid="!!errors.assignee_id || undefined"
                                ><SelectValue :placeholder="t('stage2.select_assignee')"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="unassigned"
                                    >{{ t('stage2.unassigned') }}</SelectItem
                                >
                                <SelectItem
                                    v-if="
                                        task.assignee &&
                                        !assignees.some(
                                            (user) =>
                                                user.id === task.assignee?.id,
                                        )
                                    "
                                    :value="String(task.assignee.id)"
                                >
                                    {{ task.assignee.name }} {{ t('stage2.current') }}
                                </SelectItem>
                                <SelectItem
                                    v-for="user in assignees"
                                    :key="user.id"
                                    :value="String(user.id)"
                                    >{{ user.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.assignee_id" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="task-edit-date"
                            >{{ t('stage2.due_date') }}
                            <span class="font-normal text-muted-foreground"
                                >{{ t('stage2.optional') }}</span
                            ></Label
                        >
                        <input
                            type="hidden"
                            name="due_date"
                            :value="editDueDate?.toString() ?? ''"
                        />
                        <Popover v-slot="{ close }">
                            <PopoverTrigger as-child>
                                <Button
                                    id="task-edit-date"
                                    type="button"
                                    :aria-invalid="!!errors.due_date || undefined"
                                    variant="outline"
                                    class="w-full justify-between text-left font-normal"
                                    :class="!editDueDate && 'text-muted-foreground'"
                                    :disabled="processing"
                                >
                                    <span>{{
                                        editDueDate
                                            ? dateFormatter.format(
                                                  editDueDate.toDate(
                                                      getLocalTimeZone(),
                                                  ),
                                              )
                                            : t('audit.no_date')
                                    }}</span>
                                    <CalendarDays
                                        class="size-4 shrink-0 opacity-70"
                                    />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent align="start" class="w-auto p-0">
                                <Calendar
                                    v-model="editDueDate"
                                    :default-placeholder="
                                        editDueDate ?? today(getLocalTimeZone())
                                    "
                                    layout="month-and-year"
                                    initial-focus
                                    @update:model-value="close"
                                />
                                <div class="border-t p-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="w-full"
                                        :disabled="!editDueDate"
                                        @click="
                                            editDueDate = undefined;
                                            close();
                                        "
                                        >{{ t('stage2.clear_date') }}</Button
                                    >
                                </div>
                            </PopoverContent>
                        </Popover>
                        <InputError :message="errors.due_date" />
                    </div>
                </div>
                <div class="space-y-2 border-t pt-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <Label for="task-edit-description">
                            {{ t('stage2.description') }}
                            <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                        </Label>
                    </div>
                    <input type="hidden" name="description" :value="editDescription" />
                    <AsyncVisualMarkdownEditor
                        ref="descriptionEditor"
                        v-model="editDescription"
                        :project-id="projectId"
                        :task-id="task.id"
                        :attachments="attachments"
                        :disabled="processing"
                        can-upload
                        @uploaded="router.reload({ only: ['attachments'] })"
                    />
                    <div v-if="attachments.length" class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="processing"
                                :aria-expanded="descriptionLibraryOpen"
                                @click="descriptionLibraryOpen = !descriptionLibraryOpen"
                            >
                                <Paperclip class="size-3.5" /> {{ t('messages.attachments.manage') }}
                                <ChevronDown
                                    class="size-3.5 text-muted-foreground transition-transform"
                                    :class="descriptionLibraryOpen ? 'rotate-180' : ''"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                        <TaskAttachmentLibraryPicker
                            :project-id="projectId"
                            :task-id="task.id"
                            v-if="descriptionLibraryOpen"
                            scope="description"
                            :attachments="attachments"
                            :selected-ids="[]"
                            :disabled="processing"
                            :selectable="false"
                            help-key="messages.attachments.descriptionLibraryHelp"
                            @insert="insertDescriptionAttachment"
                        />
                    </div>
                    <InputError :message="errors.description" />
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="emit('cancel')"
                    >{{ t('stage2.cancel') }}</Button>
                    <Button type="submit" :disabled="processing">
                        <LoaderCircle v-if="processing" class="size-4 animate-spin" />
                        {{ processing ? t('audit.saving') : t('audit.save_changes') }}
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
