<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

const props = withDefaults(
    defineProps<{
        userId: number;
        name: string;
        src?: string | null;
        compact?: boolean;
        stacked?: boolean;
    }>(),
    {
        src: null,
        compact: false,
        stacked: false,
    },
);

const tones = [
    'bg-[#eef1f4] text-[#526173] dark:bg-[#2a3540] dark:text-[#ced7e2]',
    'bg-[#eeedf2] text-[#625e71] dark:bg-[#34313e] dark:text-[#d3cddd]',
    'bg-[#edf1ed] text-[#53685b] dark:bg-[#2c3a33] dark:text-[#cfdbd1]',
    'bg-[#f2efe9] text-[#776b59] dark:bg-[#3d382f] dark:text-[#ded5c4]',
    'bg-[#edf0f1] text-[#58686d] dark:bg-[#2c373a] dark:text-[#d0dbdd]',
] as const;

const tone = computed(
    () => tones[Math.abs(props.userId) % tones.length] ?? tones[0],
);
const initials = computed(() => {
    const words = props.name.trim().split(/\s+/).filter(Boolean);
    const first = words[0]?.charAt(0) ?? '';
    const last =
        words.length > 1 ? (words[words.length - 1]?.charAt(0) ?? '') : '';
    return (first + last).toLocaleUpperCase() || '?';
});
</script>

<template>
    <Avatar
        :class="[
            compact ? 'size-9' : 'size-11 sm:size-12',
            stacked ? 'ring-2 ring-background' : 'ring-1 ring-border/60',
        ]"
    >
        <AvatarImage v-if="src" :src="src" :alt="name" />
        <AvatarFallback
            :class="[
                tone,
                compact ? 'text-xs' : 'text-sm',
                'font-semibold tracking-tight',
            ]"
        >
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
