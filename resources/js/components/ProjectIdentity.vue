<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { projectColors, projectIconFor } from '@/lib/projectIdentity';
import type { ProjectColor, ProjectIcon, ProjectType } from '@/types/project';

const props = withDefaults(defineProps<{
    icon?: ProjectIcon | null;
    color?: ProjectColor | null;
    logoUrl?: string | null;
    type?: ProjectType;
    size?: 'sm' | 'md' | 'lg';
}>(), { icon: null, color: null, type: 'standard', size: 'md' });

const failed = ref(false);
watch(() => props.logoUrl, () => { failed.value = false; });
const chosenIcon = computed(() => projectIconFor(props.icon, props.type));
const palette = computed(() => projectColors[props.color ?? 'slate']);
const boxSize = computed(() => ({ sm: 'size-7 rounded-md', md: 'size-11 rounded-lg', lg: 'size-16 rounded-xl' })[props.size]);
const iconSize = computed(() => ({ sm: 'size-4', md: 'size-5', lg: 'size-6' })[props.size]);
</script>

<template>
    <span aria-hidden="true" class="inline-flex shrink-0 items-center justify-center overflow-hidden" :class="[boxSize, logoUrl && !failed ? 'bg-background ring-1 ring-border/50' : palette.background, palette.foreground]">
        <!-- Fill the square project avatar; cropping is visual-only (original file is preserved). -->
        <img v-if="logoUrl && !failed" :key="logoUrl" :src="logoUrl" alt="" loading="lazy"
            class="block size-full object-cover object-center" @error="failed = true" />
        <component v-else :is="chosenIcon" :class="iconSize" />
    </span>
</template>
