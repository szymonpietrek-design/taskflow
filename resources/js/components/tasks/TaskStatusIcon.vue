<script setup lang="ts">
import { CheckCircle2, Circle, CircleDot, CircleX } from '@lucide/vue';
import type { TaskStatusSummary } from '@/types/task';

defineProps<{
    category: TaskStatusSummary['category'];
}>();
</script>

<template>
    <CheckCircle2
        v-if="category === 'done'"
        class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
        aria-hidden="true"
    />
    <CircleDot
        v-else-if="category === 'in_progress'"
        class="size-4 shrink-0 text-blue-600 dark:text-blue-400"
        aria-hidden="true"
    />
    <CircleX
        v-else-if="category === 'canceled'"
        class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500"
        aria-hidden="true"
    />
    <Circle
        v-else
        class="size-4 shrink-0 text-slate-400 dark:text-slate-500"
        aria-hidden="true"
    />
</template>
