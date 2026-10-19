<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { userAvatarTone } from '@/lib/userAvatarTone';

const props = withDefaults(
    defineProps<{
        userId: number;
        name: string;
        src?: string | null;
        compact?: boolean;
        small?: boolean;
        stacked?: boolean;
    }>(),
    {
        src: null,
        compact: false,
        small: false,
        stacked: false,
    },
);

const tone = computed(() => userAvatarTone(props.userId));
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
            small ? 'size-7' : compact ? 'size-9' : 'size-11 sm:size-12',
            stacked ? 'ring-2 ring-background' : 'ring-1 ring-border/60',
        ]"
    >
        <AvatarImage v-if="src" :src="src" :alt="name" />
        <AvatarFallback
            :class="[
                tone,
                small ? 'text-[10px]' : compact ? 'text-xs' : 'text-sm',
                'font-semibold tracking-tight',
            ]"
        >
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
