<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form } from '@inertiajs/vue3';
import type { DateValue } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import { CalendarDays, LoaderCircle } from '@lucide/vue';
import { computed, ref, shallowRef } from 'vue';
import TaskMarkdown from '@/components/tasks/TaskMarkdown.vue';
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
import type { TaskDetail, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    task: TaskDetail;
    assignees: TaskUserSummary[];
}>();

const emit = defineEmits<{ saved: []; cancel: [] }>();
const updateUrl = computed(
    () => `/projects/${props.projectId}/tasks/${props.task.id}`,
);
// Controlled shadcn-vue fields need hidden inputs for Inertia's Form payload.
const editPriority = ref(String(props.task.priority));
const editDescription = ref(props.task.description ?? '');
const descriptionTab = ref<'edit' | 'preview'>('edit');
const descriptionPreview = ref('');
const previewLoading = ref(false);
const previewError = ref(false);
let previewRequestId = 0;

async function showDescriptionPreview(): Promise<void> {
    descriptionTab.value = 'preview';
    previewError.value = false;
    descriptionPreview.value = '';
    const requestId = ++previewRequestId;
    if (!editDescription.value.trim()) return;

    previewLoading.value = true;
    try {
        // Use the current cookie: Laravel may regenerate the CSRF token after login
        // without a full page reload (Inertia navigation).
        const xsrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
        const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length)) : null;
        const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!xsrfToken && !metaToken) throw new Error('Missing CSRF token');
        const response = await fetch(`/projects/${props.projectId}/tasks/${props.task.id}/description-preview`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : { 'X-CSRF-TOKEN': metaToken! }),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ description: editDescription.value }),
        });
        if (!response.ok) throw new Error(`Preview request failed: ${response.status}`);
        const data: { html?: unknown } = await response.json();
        if (typeof data.html !== 'string') throw new Error('Invalid Markdown preview response');
        if (requestId === previewRequestId) descriptionPreview.value = data.html;
    } catch {
        if (requestId === previewRequestId) previewError.value = true;
    } finally {
        if (requestId === previewRequestId) previewLoading.value = false;
    }
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

const priorities = [
    { value: 1, label: 'Low' },
    { value: 2, label: 'Normal' },
    { value: 3, label: 'High' },
    { value: 4, label: 'Urgent' },
];
const { t, locale, formatText, translateLiteral, displayLabel } = useI18n();
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
                        :default-value="task.title"
                        :disabled="processing"
                    />
                    <InputError :message="errors.title" />
                </div>
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <Label for="task-edit-description">
                            {{ t('stage2.description') }}
                            <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                        </Label>
                        <div class="inline-flex rounded-md border p-0.5" role="group" :aria-label="t('taskDetails.markdownMode')">
                            <Button
                                type="button" size="sm" :variant="descriptionTab === 'edit' ? 'secondary' : 'ghost'"
                                :aria-pressed="descriptionTab === 'edit'" :disabled="processing"
                                @click="descriptionTab = 'edit'"
                            >{{ t('taskDetails.editMarkdown') }}</Button>
                            <Button
                                type="button" size="sm" :variant="descriptionTab === 'preview' ? 'secondary' : 'ghost'"
                                :aria-pressed="descriptionTab === 'preview'" :disabled="processing"
                                @click="showDescriptionPreview"
                            >{{ t('taskDetails.previewMarkdown') }}</Button>
                        </div>
                    </div>
                    <textarea
                        v-show="descriptionTab === 'edit'"
                        id="task-edit-description"
                        v-model="editDescription"
                        name="description"
                        rows="8"
                        maxlength="10000"
                        :disabled="processing"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <div v-if="descriptionTab === 'preview'" class="min-h-36 rounded-md border px-4 py-3">
                        <p v-if="previewLoading" role="status" class="text-sm text-muted-foreground">{{ t('taskDetails.loadingPreview') }}</p>
                        <p v-else-if="previewError" role="alert" class="text-sm text-destructive">{{ t('taskDetails.previewError') }}</p>
                        <TaskMarkdown v-else-if="descriptionPreview" :html="descriptionPreview" />
                        <p v-else class="text-sm text-muted-foreground">{{ t('taskDetails.emptyPreview') }}</p>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ t('taskDetails.markdownHelp') }}</p>
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="task-edit-priority">{{ t('stage2.priority') }}</Label>
                        <input
                            type="hidden"
                            name="priority"
                            :value="editPriority"
                        />
                        <Select v-model="editPriority" :disabled="processing">
                            <SelectTrigger
                                id="task-edit-priority"
                                class="w-full"
                                ><SelectValue :placeholder="t('stage2.select_priority')"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="priority in priorities"
                                    :key="priority.value"
                                    :value="String(priority.value)"
                                    >{{ displayLabel(priority.label) }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.priority" />
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
                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="processing"
                        ><LoaderCircle
                            v-if="processing"
                            class="size-4 animate-spin"
                        />{{ processing ? t('audit.saving') : t('audit.save_changes') }}</Button
                    >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="emit('cancel')"
                        >{{ t('stage2.cancel') }}</Button
                    >
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
