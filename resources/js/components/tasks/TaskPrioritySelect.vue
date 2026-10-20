<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import TaskPriorityLabel from '@/components/tasks/TaskPriorityLabel.vue';
import { TASK_PRIORITIES, taskPriorityDotClass, taskPriorityTextClass } from '@/lib/taskVisuals';
import type { TaskPriorityValue } from '@/types/task';

// Shared with the task list and header: priority colours come from taskVisuals.
const props = defineProps<{
    id: string;
    disabled?: boolean;
    invalid?: boolean;
}>();
const model = defineModel<string>({ required: true });
const { t } = useI18n();
const selected = computed(() => TASK_PRIORITIES.find((priority) => String(priority) === model.value));
</script>

<template>
    <Select v-model="model" :disabled="props.disabled">
        <SelectTrigger :id="props.id" class="w-full" :aria-invalid="props.invalid || undefined">
            <span class="flex min-w-0 flex-1 items-center gap-2 text-left">
                <span
                    v-if="selected"
                    class="size-2 shrink-0 rounded-full"
                    :class="taskPriorityDotClass(selected)"
                    aria-hidden="true"
                />
                <SelectValue class="min-w-0 flex-1 text-left" :class="selected ? taskPriorityTextClass(selected) : undefined" :placeholder="t('stage2.select_priority')" />
            </span>
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="priority in TASK_PRIORITIES" :key="priority" :value="String(priority)">
                <TaskPriorityLabel :priority="priority" />
            </SelectItem>
        </SelectContent>
    </Select>
</template>
