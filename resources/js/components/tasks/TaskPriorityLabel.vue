<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { taskPriorityDotClass, taskPriorityLabelKey, taskPriorityTextClass } from '@/lib/taskVisuals';
import type { TaskPriorityValue } from '@/types/task';

const props = withDefaults(defineProps<{
    priority: TaskPriorityValue;
    prefix?: boolean;
}>(), { prefix: false });
const { t } = useI18n();
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-1.5 whitespace-nowrap" :class="taskPriorityTextClass(props.priority)">
        <span class="size-2 shrink-0 rounded-full" :class="taskPriorityDotClass(props.priority)" aria-hidden="true" />
        <span v-if="props.prefix" class="text-muted-foreground">{{ t('stage2.priority') }}:</span>
        <span>{{ t(taskPriorityLabelKey(props.priority)) }}</span>
    </span>
</template>
