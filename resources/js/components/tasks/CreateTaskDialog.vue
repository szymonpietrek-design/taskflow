<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import TaskTypeLabel from '@/components/tasks/TaskTypeLabel.vue';
import TaskPrioritySelect from '@/components/tasks/TaskPrioritySelect.vue';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import type { TaskTypeValue } from '@/types/task';
import type {
    TaskParentSummary,
    TaskStatusSummary,
    TaskUserSummary,
} from '@/types/task';

const props = defineProps<{
    open: boolean;
    projectId: number;
    statuses: TaskStatusSummary[];
    assignees: TaskUserSummary[];
    parentTasks: TaskParentSummary[];
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const { t } = useI18n();

// Both entry points submit to the same Laravel StoreTaskRequest/CreateTask action.
const form = useForm({
    title: '',
    description: '',
    type: '1',
    priority: '2',
    status_id: 'default',
    assignee_id: 'unassigned',
    parent_id: 'none',
    due_date: '',
});
const selectedType = computed(() => Number(form.type) as TaskTypeValue);
const fieldsContainer = ref<HTMLElement | null>(null);

function focusFirstError(): void {
    void nextTick(() => {
        fieldsContainer.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    });
}

watch(() => props.open, (open) => {
    if (!open) return;
    form.reset();
    form.clearErrors();
});

function setOpen(open: boolean): void {
    if (form.processing) return;
    emit('update:open', open);
}

function submit(): void {
    if (form.processing) return;
    form.transform((data) => ({
        title: data.title.trim(),
        description: data.description.trim() || null,
        type: Number(data.type),
        priority: Number(data.priority),
        status_id: data.status_id === 'default' ? null : Number(data.status_id),
        assignee_id: data.assignee_id === 'unassigned' ? null : Number(data.assignee_id),
        parent_id: data.parent_id === 'none' ? null : Number(data.parent_id),
        due_date: data.due_date || null,
    })).post(`/projects/${props.projectId}/tasks`, {
        preserveScroll: true,
        onError: focusFirstError,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
            form.clearErrors();
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="setOpen">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-h-[min(90dvh,820px)] sm:max-w-[44rem]"
            :show-close-button="!form.processing"
        >
            <DialogHeader class="shrink-0 px-6 pb-4 pt-6 pr-12">
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="size-4" /> {{ t('stage2.new_task') }}
                </DialogTitle>
                <DialogDescription>{{ t('followup.task_numbers') }}</DialogDescription>
            </DialogHeader>

            <form class="flex min-h-0 flex-1 flex-col" :aria-busy="form.processing" @submit.prevent="submit">
                <div ref="fieldsContainer" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 pb-5">
                    <div class="grid gap-2">
                        <Label for="create-task-title">{{ t('stage2.title') }}</Label>
                        <Input
                            id="create-task-title"
                            v-model="form.title"
                            maxlength="200"
                            required
                            :aria-invalid="!!form.errors.title || undefined"
                            autofocus
                            :disabled="form.processing"
                            :placeholder="t('stage2.implement_dashboard')"
                        />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-task-description">
                            {{ t('stage2.description') }}
                            <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                        </Label>
                        <textarea
                            id="create-task-description"
                            v-model="form.description"
                            rows="3"
                            maxlength="10000"
                            :disabled="form.processing"
                            :aria-invalid="!!form.errors.description || undefined"
                            :placeholder="t('stage2.optional_details')"
                            class="flex min-h-20 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="create-task-type">{{ t('stage2.type') }}</Label>
                            <Select v-model="form.type" :disabled="form.processing">
                                <SelectTrigger id="create-task-type" class="w-full" :aria-invalid="!!form.errors.type || undefined">
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
                            <InputError :message="form.errors.type" />
                        </div>
                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="create-task-priority">{{ t('stage2.priority') }}</Label>
                            <TaskPrioritySelect
                                id="create-task-priority"
                                v-model="form.priority"
                                :disabled="form.processing"
                                :invalid="!!form.errors.priority"
                            />
                            <InputError :message="form.errors.priority" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="create-task-status">
                                {{ t('stage2.status') }}
                                <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                            </Label>
                            <Select v-model="form.status_id" :disabled="form.processing">
                                <SelectTrigger id="create-task-status" class="w-full" :aria-invalid="!!form.errors.status_id || undefined">
                                    <SelectValue :placeholder="t('stage2.select_status')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="default">{{ t('stage2.default_todo') }}</SelectItem>
                                    <SelectItem v-for="status in statuses" :key="status.id" :value="String(status.id)">
                                        {{ status.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.status_id" />
                        </div>
                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="create-task-assignee">
                                {{ t('stage2.assignee') }}
                                <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                            </Label>
                            <Select v-model="form.assignee_id" :disabled="form.processing">
                                <SelectTrigger id="create-task-assignee" class="w-full" :aria-invalid="!!form.errors.assignee_id || undefined">
                                    <SelectValue :placeholder="t('stage2.select_assignee')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unassigned">{{ t('stage2.unassigned') }}</SelectItem>
                                    <SelectItem v-for="person in assignees" :key="person.id" :value="String(person.id)">
                                        {{ person.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.assignee_id" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="create-task-parent">
                                {{ t('stage2.parent_task') }}
                                <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                            </Label>
                            <Select v-model="form.parent_id" :disabled="form.processing">
                                <SelectTrigger id="create-task-parent" class="w-full" :aria-invalid="!!form.errors.parent_id || undefined">
                                    <SelectValue :placeholder="t('stage2.select_parent_task')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{{ t('stage2.none_regular_task') }}</SelectItem>
                                    <SelectItem v-for="task in parentTasks" :key="task.id" :value="String(task.id)">
                                        {{ task.reference }} — {{ task.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.parent_id" />
                        </div>
                        <div class="grid min-w-0 content-start gap-2">
                            <Label for="create-task-due-date">
                                {{ t('stage2.due_date') }}
                                <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                            </Label>
                            <DatePicker
                                id="create-task-due-date"
                                v-model="form.due_date"
                                clearable
                                :disabled="form.processing"
                                :invalid="!!form.errors.due_date"
                                :placeholder="t('followup.date')"
                            />
                            <InputError :message="form.errors.due_date" />
                        </div>
                    </div>
                </div>
                <DialogFooter class="shrink-0 gap-2 border-t bg-background px-6 py-4 sm:gap-2">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="setOpen(false)">
                        {{ t('stage2.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        <Plus v-else class="size-4" />
                        {{ form.processing ? t('followup.creating') : t('stage2.create_task') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
