<script setup lang="ts">
import './task-markdown-typography.css';
// Only pass HTML rendered on the server with TaskMarkdown (HTML stripped, unsafe links disabled).
const props = defineProps<{ html: string; zoomImages?: boolean }>();
const emit = defineEmits<{ imageClick: [url: string] }>();
function openEmbeddedImage(event: MouseEvent): void {
    if (!props.zoomImages) return;
    const element = event.target;
    if (!(element instanceof HTMLImageElement)) return;
    const src = element.getAttribute('src');
    if (src) emit('imageClick', src);
}
</script>

<template>
    <div class="task-markdown min-w-0 break-words text-sm leading-6" :class="{ 'task-markdown--zoom': zoomImages }" v-html="html" @click="openEmbeddedImage" />
</template>

