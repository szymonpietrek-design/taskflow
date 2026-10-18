<script setup lang="ts">
import { Bug, ListTodo, Sparkles, Wrench } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import type { TaskTypeValue } from '@/types/task';

const props = withDefaults(defineProps<{
    type: TaskTypeValue;
    variant?: 'inline' | 'badge';
    iconOnly?: boolean;
}>(), {
    variant: 'inline',
    iconOnly: false,
});

const { t } = useI18n();
const labels: Record<TaskTypeValue, string> = {
    1: 'stage2.task',
    2: 'stage2.feature',
    3: 'stage2.improvement',
    4: 'stage2.bug',
};
const label = computed(() => t(labels[props.type] ?? labels[1]));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1.5 align-middle"
        :class="[
            variant === 'badge' && 'rounded-full border border-border bg-background px-2 py-0.5 text-xs font-medium text-foreground',
            iconOnly && 'justify-center',
        ]"
        :aria-label="iconOnly ? label : undefined"
        :title="iconOnly ? label : undefined"
    >
        <Bug
            v-if="type === 4"
            class="size-3.5 shrink-0 text-rose-600 dark:text-rose-400"
            aria-hidden="true"
        />
        <Sparkles
            v-else-if="type === 2"
            class="size-3.5 shrink-0 text-violet-600 dark:text-violet-400"
            aria-hidden="true"
        />
        <Wrench
            v-else-if="type === 3"
            class="size-3.5 shrink-0 text-amber-600 dark:text-amber-400"
            aria-hidden="true"
        />
        <ListTodo
            v-else
            class="size-3.5 shrink-0 text-slate-500 dark:text-slate-400"
            aria-hidden="true"
        />
        <span v-if="!iconOnly">{{ label }}</span>
    </span>
</template>
