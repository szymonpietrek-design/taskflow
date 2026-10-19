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
import { taskPriorityDotClass } from '@/lib/taskVisuals';
import type { TaskPriorityValue } from '@/types/task';

// Shared with the task list and header: priority colours come from taskVisuals.
const props = defineProps<{
    id: string;
    disabled?: boolean;
    invalid?: boolean;
}>();
const model = defineModel<string>({ required: true });
const { t } = useI18n();
const priorities = [1, 2, 3, 4] as const;
const labelKeys = {
    1: 'stage2.low',
    2: 'stage2.normal',
    3: 'stage2.high',
    4: 'stage2.urgent',
} as const;
const selected = computed(() => priorities.find((priority) => String(priority) === model.value));
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
                <SelectValue class="min-w-0 flex-1 text-left" :placeholder="t('stage2.select_priority')" />
            </span>
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="priority in priorities" :key="priority" :value="String(priority)">
                <span class="flex items-center gap-2">
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :class="taskPriorityDotClass(priority)"
                        aria-hidden="true"
                    />
                    {{ t(labelKeys[priority]) }}
                </span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
