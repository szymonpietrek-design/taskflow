<script setup lang="ts">
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

<style scoped>
.task-markdown :deep(h1), .task-markdown :deep(h2), .task-markdown :deep(h3) {
    font-weight: 600;
    line-height: 1.4;
    margin: 1.2rem 0 0.5rem;
}
.task-markdown :deep(h1) { font-size: 1.375rem; }
.task-markdown :deep(h2) { font-size: 1.2rem; }
.task-markdown :deep(h3) { font-size: 1.05rem; }
.task-markdown :deep(> :first-child) { margin-top: 0; }
.task-markdown :deep(> :last-child) { margin-bottom: 0; }
.task-markdown :deep(p) { margin: 0 0 0.75rem; }
.task-markdown :deep(ul) { list-style: disc; padding-left: 1.5rem; margin: 0.5rem 0 0.875rem; }
.task-markdown :deep(ol) { list-style: decimal; padding-left: 1.5rem; margin: 0.5rem 0 0.875rem; }
.task-markdown :deep(li) { padding-left: 0.15rem; margin: 0.125rem 0; }
.task-markdown :deep(li > p) { margin-bottom: 0.25rem; }
.task-markdown :deep(a) { text-decoration: underline; text-underline-offset: 2px; overflow-wrap: anywhere; }
.task-markdown :deep(a:hover) { color: var(--primary); }
.task-markdown :deep(strong) { font-weight: 650; }
.task-markdown :deep(blockquote) { border-left: 3px solid var(--border); padding-left: 1rem; color: var(--muted-foreground); margin: 0.75rem 0; }
.task-markdown :deep(code) { font-size: 0.875em; background: var(--muted); padding: 0.125rem 0.3rem; border-radius: 0.25rem; overflow-wrap: anywhere; }
.task-markdown :deep(pre) { background: var(--muted); padding: 0.875rem; border-radius: 0.5rem; overflow-x: auto; margin: 0.75rem 0; }
.task-markdown :deep(pre code) { padding: 0; background: transparent; overflow-wrap: normal; }
.task-markdown :deep(table) { display: block; overflow-x: auto; border-collapse: collapse; max-width: 100%; margin: 0.75rem 0; }
.task-markdown :deep(th), .task-markdown :deep(td) { border: 1px solid var(--border); padding: 0.375rem 0.625rem; text-align: left; }
.task-markdown :deep(th) { background: var(--muted); font-weight: 600; }
.task-markdown :deep(img) { max-width: 100%; max-height: 32rem; height: auto; border-radius: 0.375rem; object-fit: contain; margin: 0.75rem 0; }
.task-markdown--zoom :deep(img) { cursor: zoom-in; }
.task-markdown :deep(hr) { border-top: 1px solid var(--border); margin: 1rem 0; }
</style>
