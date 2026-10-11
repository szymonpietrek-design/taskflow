<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const value = defineModel<string>({ default: '' });
</script>

<template>
    <textarea v-model="value" v-bind="$attrs" data-slot="textarea"
        :class="cn('flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50', props.class)" />
</template>
