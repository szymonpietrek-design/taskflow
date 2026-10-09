<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { DateValue } from '@internationalized/date';
import { DateFormatter, getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarDays, LoaderCircle } from '@lucide/vue';
import { computed, ref, shallowRef } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { TaskDetail, TaskUserSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    task: TaskDetail;
    assignees: TaskUserSummary[];
}>();

const emit = defineEmits<{ saved: []; cancel: [] }>();
const updateUrl = computed(() => `/projects/${props.projectId}/tasks/${props.task.id}`);
// Controlled shadcn-vue fields need hidden inputs for Inertia's Form payload.
const editPriority = ref(String(props.task.priority));
const editAssignee = ref(props.task.assignee ? String(props.task.assignee.id) : 'unassigned');
const editDueDate = shallowRef<DateValue | undefined>(
    props.task.due_date ? parseDate(props.task.due_date.slice(0, 10)) : undefined,
);
const dateFormatter = new DateFormatter('en-US', { dateStyle: 'medium' });
const notifyError = () => toast.error('Could not update the task. Check the form and try again.');
const stayOnTask = { preserveScroll: true, preserveState: true };

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
            <Form :action="updateUrl" method="patch" :options="stayOnTask" class="space-y-4" @success="emit('saved')" @error="notifyError" v-slot="{ errors, processing }">
                <div class="space-y-2">
                    <Label for="task-edit-title">Title</Label>
                    <Input id="task-edit-title" name="title" type="text" required maxlength="200" :default-value="task.title" :disabled="processing" />
                    <InputError :message="errors.title" />
                </div>
                <div class="space-y-2">
                    <Label for="task-edit-description">Description</Label>
                    <textarea id="task-edit-description" name="description" rows="5" maxlength="10000" :value="task.description ?? ''" :disabled="processing"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="task-edit-priority">Priority</Label>
                        <input type="hidden" name="priority" :value="editPriority" />
                        <Select v-model="editPriority" :disabled="processing">
                            <SelectTrigger id="task-edit-priority" class="w-full"><SelectValue placeholder="Select priority" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="priority in priorities" :key="priority.value" :value="String(priority.value)">{{ priority.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.priority" />
                    </div>
                    <div class="space-y-2">
                        <Label for="task-edit-assignee">Assigned to</Label>
                        <input type="hidden" name="assignee_id" :value="editAssignee === 'unassigned' ? '' : editAssignee" />
                        <Select v-model="editAssignee" :disabled="processing">
                            <SelectTrigger id="task-edit-assignee" class="w-full"><SelectValue placeholder="Select assignee" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="unassigned">Unassigned</SelectItem>
                                <SelectItem v-if="task.assignee && !assignees.some(user => user.id === task.assignee?.id)" :value="String(task.assignee.id)">
                                    {{ task.assignee.name }} (current)
                                </SelectItem>
                                <SelectItem v-for="user in assignees" :key="user.id" :value="String(user.id)">{{ user.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.assignee_id" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="task-edit-date">Due date</Label>
                    <input type="hidden" name="due_date" :value="editDueDate?.toString() ?? ''" />
                    <Popover v-slot="{ close }">
                        <PopoverTrigger as-child>
                            <Button id="task-edit-date" type="button" variant="outline" class="w-full justify-between text-left font-normal"
                                :class="!editDueDate && 'text-muted-foreground'" :disabled="processing">
                                <span>{{ editDueDate ? dateFormatter.format(editDueDate.toDate(getLocalTimeZone())) : 'Select a date' }}</span>
                                <CalendarDays class="size-4 shrink-0 opacity-70" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent align="start" class="w-auto p-0">
                            <Calendar v-model="editDueDate" :default-placeholder="editDueDate ?? today(getLocalTimeZone())"
                                layout="month-and-year" initial-focus @update:model-value="close" />
                            <div class="border-t p-2">
                                <Button type="button" variant="ghost" size="sm" class="w-full" :disabled="!editDueDate"
                                    @click="editDueDate = undefined; close()">Clear date</Button>
                            </div>
                        </PopoverContent>
                    </Popover>
                    <InputError :message="errors.due_date" />
                </div>
                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="processing"><LoaderCircle v-if="processing" class="size-4 animate-spin" />{{ processing ? 'Saving…' : 'Save changes' }}</Button>
                    <Button type="button" variant="outline" :disabled="processing" @click="emit('cancel')">Cancel</Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
