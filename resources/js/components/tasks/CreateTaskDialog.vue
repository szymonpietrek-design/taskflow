<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
            class="max-h-[min(90dvh,780px)] overflow-y-auto sm:max-w-[36rem]"
            :show-close-button="!form.processing"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="size-4" /> {{ t('stage2.new_task') }}
                </DialogTitle>
                <DialogDescription>{{ t('followup.task_numbers') }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="create-task-title">{{ t('stage2.title') }}</Label>
                    <Input
                        id="create-task-title"
                        v-model="form.title"
                        maxlength="200"
                        required
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
                        :placeholder="t('stage2.optional_details')"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-2">
                        <Label for="create-task-type">{{ t('stage2.type') }}</Label>
                        <Select v-model="form.type" :disabled="form.processing">
                            <SelectTrigger id="create-task-type" class="w-full">
                                <SelectValue :placeholder="t('stage2.select_type')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">{{ t('stage2.task') }}</SelectItem>
                                <SelectItem value="2">{{ t('stage2.feature') }}</SelectItem>
                                <SelectItem value="3">{{ t('stage2.improvement') }}</SelectItem>
                                <SelectItem value="4">{{ t('stage2.bug') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="create-task-priority">{{ t('stage2.priority') }}</Label>
                        <Select v-model="form.priority" :disabled="form.processing">
                            <SelectTrigger id="create-task-priority" class="w-full">
                                <SelectValue :placeholder="t('stage2.select_priority')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">{{ t('stage2.low') }}</SelectItem>
                                <SelectItem value="2">{{ t('stage2.normal') }}</SelectItem>
                                <SelectItem value="3">{{ t('stage2.high') }}</SelectItem>
                                <SelectItem value="4">{{ t('stage2.urgent') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.priority" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="create-task-status">
                        {{ t('stage2.status') }}
                        <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                    </Label>
                    <Select v-model="form.status_id" :disabled="form.processing">
                        <SelectTrigger id="create-task-status" class="w-full">
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
                <div class="grid gap-2">
                    <Label for="create-task-assignee">
                        {{ t('stage2.assignee') }}
                        <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                    </Label>
                    <Select v-model="form.assignee_id" :disabled="form.processing">
                        <SelectTrigger id="create-task-assignee" class="w-full">
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
                <div class="grid gap-2">
                    <Label for="create-task-parent">
                        {{ t('stage2.parent_task') }}
                        <span class="font-normal text-muted-foreground">{{ t('stage2.optional') }}</span>
                    </Label>
                    <Select v-model="form.parent_id" :disabled="form.processing">
                        <SelectTrigger id="create-task-parent" class="w-full">
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
                <div class="grid gap-2">
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
                <DialogFooter class="gap-3 border-t pt-4 sm:gap-3">
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
