<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    Circle,
    LoaderCircle,
    Plus,
} from '@lucide/vue';
import type { DateValue } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    today,
} from '@internationalized/date';
import { toast } from 'vue-sonner';
import { computed, ref, shallowRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import type {
    TaskStatusSummary,
    TaskSubtaskSummary,
    TaskUserSummary,
} from '@/types/task';

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
const subtaskPriority = ref('2');
const subtaskStatus = ref('default');
const subtaskAssignee = ref('unassigned');
const subtaskDueDate = shallowRef<DateValue | undefined>();
const dateFormatter = computed(() => new DateFormatter(locale.value === 'pl' ? 'pl-PL' : 'en-US', { dateStyle: 'medium' }));
const notifyError = () =>
    toast.error(t('audit.could_not_create_the_subtask_check_the_form_and_try_again'));
const stayOnTask = { preserveScroll: true, preserveState: true };
const percentage = computed(() =>
    props.progress.total === 0
        ? 0
        : Math.round((props.progress.done / props.progress.total) * 100),
);

function taskUrl(id: number): string {
    return `/projects/${props.projectId}/tasks/${id}`;
}
const { t, locale, formatText, translateLiteral } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle>{{ t('stage2.subtasks') }}</CardTitle>
                    <CardDescription v-if="progress.total">
                        {{ formatText('audit.count_completed', { completed: progress.done, total: progress.total }) }} ({{ percentage }}%)
                    </CardDescription>
                    <CardDescription v-else
                        >{{ t('stage2.split_this_task_into_smaller_steps') }}</CardDescription
                    >
                </div>
                <Button
                    v-if="canCreate && !creating"
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="creating = true"
                >
                    <Plus class="size-4" /> {{ t('stage2.add_subtask') }}
                </Button>
            </div>
            <div
                v-if="progress.total"
                class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-valuenow="percentage"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t('stage2.subtask_completion')"
            >
                <div
                    class="h-full rounded-full bg-primary transition-all"
                    :style="{ width: `${percentage}%` }"
                />
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <ul v-if="subtasks.length" class="divide-y">
                <li
                    v-for="child in subtasks"
                    :key="child.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <Link
                        :href="taskUrl(child.id)"
                        class="flex min-w-0 flex-1 items-start gap-2 text-sm hover:underline"
                    >
                        <CheckCircle2
                            v-if="child.status.category === 'done'"
                            class="mt-0.5 size-4 shrink-0 text-primary"
                        />
                        <Circle
                            v-else
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0">
                            <span class="block font-medium break-words">{{
                                child.title
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ child.reference }}
                                <template v-if="child.assignee">
                                    · {{ child.assignee.name }}</template
                                >
                                <template v-if="child.due_date">
                                    · {{ t('stage2.due_date') }} {{ child.due_date }}</template
                                >
                            </span>
                        </span>
                    </Link>
                    <Badge
                        :variant="
                            child.status.category === 'done'
                                ? 'default'
                                : 'secondary'
                        "
                        >{{ translateLiteral(child.status.name) }}</Badge
                    >
                </li>
            </ul>
            <p v-else-if="!creating" class="text-sm text-muted-foreground">
                {{ t('stage2.no_subtasks_yet') }}
            </p>

            <Form
                v-if="canCreate && creating"
                :action="`/projects/${projectId}/tasks`"
                method="post"
                class="space-y-3 rounded-md border bg-muted/20 p-4"
                :options="stayOnTask"
                reset-on-success
                @error="notifyError"
                v-slot="{ errors, processing }"
            >
                <input type="hidden" name="parent_id" :value="parentTaskId" />
                <input type="hidden" name="type" value="1" />
                <div class="grid gap-1.5">
                    <Label for="subtask-title">{{ t('stage2.subtask_title') }}</Label>
                    <Input
                        id="subtask-title"
                        name="title"
                        maxlength="200"
                        required
                        :placeholder="t('stage2.write_tests')"
                    />
                    <InputError :message="errors.title || errors.parent_id" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="subtask-priority">{{ t('stage2.priority') }}</Label>
                        <input
                            type="hidden"
                            name="priority"
                            :value="subtaskPriority"
                        />
                        <Select
                            v-model="subtaskPriority"
                            :disabled="processing"
                        >
                            <SelectTrigger id="subtask-priority" class="w-full"
                                ><SelectValue :placeholder="t('stage2.select_priority')"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="2">{{ t('stage2.normal') }}</SelectItem>
                                <SelectItem value="1">{{ t('stage2.low') }}</SelectItem>
                                <SelectItem value="3">{{ t('stage2.high') }}</SelectItem>
                                <SelectItem value="4">{{ t('stage2.urgent') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.priority" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="subtask-status"
                            >{{ t('stage2.status') }}
                            <span class="font-normal text-muted-foreground"
                                >{{ t('stage2.optional') }}</span
                            ></Label
                        >
                        <input
                            type="hidden"
                            name="status_id"
                            :value="
                                subtaskStatus === 'default' ? '' : subtaskStatus
                            "
                        />
                        <Select v-model="subtaskStatus" :disabled="processing">
                            <SelectTrigger id="subtask-status" class="w-full"
                                ><SelectValue :placeholder="t('stage2.select_status')"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="default"
                                    >{{ t('stage2.default_todo') }}</SelectItem
                                >
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status.id"
                                    :value="String(status.id)"
                                    >{{ status.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.status_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="subtask-assignee"
                            >{{ t('stage2.assignee') }}
                            <span class="font-normal text-muted-foreground"
                                >{{ t('stage2.optional') }}</span
                            ></Label
                        >
                        <input
                            type="hidden"
                            name="assignee_id"
                            :value="
                                subtaskAssignee === 'unassigned'
                                    ? ''
                                    : subtaskAssignee
                            "
                        />
                        <Select
                            v-model="subtaskAssignee"
                            :disabled="processing"
                        >
                            <SelectTrigger id="subtask-assignee" class="w-full"
                                ><SelectValue :placeholder="t('stage2.select_assignee')"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="unassigned"
                                    >{{ t('stage2.unassigned') }}</SelectItem
                                >
                                <SelectItem
                                    v-for="person in assignees"
                                    :key="person.id"
                                    :value="String(person.id)"
                                    >{{ person.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.assignee_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="subtask-due-date"
                            >{{ t('stage2.due_date') }}
                            <span class="font-normal text-muted-foreground"
                                >{{ t('stage2.optional') }}</span
                            ></Label
                        >
                        <input
                            type="hidden"
                            name="due_date"
                            :value="subtaskDueDate?.toString() ?? ''"
                        />
                        <Popover v-slot="{ close }">
                            <PopoverTrigger as-child>
                                <Button
                                    id="subtask-due-date"
                                    type="button"
                                    variant="outline"
                                    class="w-full justify-between text-left font-normal"
                                    :class="
                                        !subtaskDueDate &&
                                        'text-muted-foreground'
                                    "
                                    :disabled="processing"
                                >
                                    <span>{{
                                        subtaskDueDate
                                            ? dateFormatter.format(
                                                  subtaskDueDate.toDate(
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
                                    v-model="subtaskDueDate"
                                    :default-placeholder="
                                        today(getLocalTimeZone())
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
                                        :disabled="!subtaskDueDate"
                                        @click="
                                            subtaskDueDate = undefined;
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
                <div class="flex gap-2">
                    <Button size="sm" :disabled="processing"
                        ><LoaderCircle
                            v-if="processing"
                            class="size-4 animate-spin"
                        /><Plus v-else class="size-4" />{{
                            processing ? t('audit.creating') : t('fix.create_subtask')
                        }}</Button
                    >
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="processing"
                        @click="creating = false"
                        >{{ t('stage2.cancel') }}</Button
                    >
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
