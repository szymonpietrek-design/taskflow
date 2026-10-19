<script setup lang="ts">
import { Crepe } from '@milkdown/crepe';
import { editorViewCtx } from '@milkdown/kit/core';
import { TextSelection } from '@milkdown/kit/prose/state';
import { setBlockType, toggleMark } from '@milkdown/kit/prose/commands';
import { liftListItem, wrapInList } from '@milkdown/kit/prose/schema-list';
import { insert, replaceAll } from '@milkdown/kit/utils';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from '@/composables/useI18n';
import { Button } from '@/components/ui/button';
import { Bold, Check, ChevronDown, Code2, Heading1, Heading2, Heading3, Image as ImageIcon, Italic, Link2, List, ListOrdered, Plus, Quote, Table2, Minus } from '@lucide/vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { TaskAttachmentSummary } from '@/types/task';
import '@milkdown/crepe/theme/common/style.css';
import '@milkdown/crepe/theme/frame.css';
import './task-markdown-typography.css';

const props = withDefaults(defineProps<{
    modelValue: string;
    projectId: number;
    taskId: number;
    attachments: TaskAttachmentSummary[];
    placeholder?: string;
    disabled?: boolean;
    canUpload?: boolean;
    compact?: boolean;
    maxLength?: number;
    // Commands from parents are declarative, so refs to async wrappers are not needed.
    insertRequests?: Array<{ id: number; file: TaskAttachmentSummary; image: boolean }>;
}>(), { placeholder: '', disabled: false, canUpload: false, compact: false, maxLength: 10000 });

const emit = defineEmits<{
    'update:modelValue': [value: string];
    uploaded: [attachment: TaskAttachmentSummary];
    uploading: [value: boolean];
}>();
const { t } = useI18n();
const root = ref<HTMLElement | null>(null);
const imagePicker = ref<HTMLInputElement | null>(null);
const uploadingImage = ref(false);
let activeUploads = 0;
const sourceMode = ref(false);
const ready = ref(false);
const localUploads = ref<TaskAttachmentSummary[]>([]);
const allAttachments = computed(() => [...props.attachments, ...localUploads.value.filter(a => !props.attachments.some(b => b.id === a.id))]);
let crepe: Crepe | null = null;
let updatingFromSource = false;
const queuedInsertions: Array<{ id: number; file: TaskAttachmentSummary; image: boolean }> = [];
const handledInsertions = new Set<number>();

function flushQueuedInsertions(): void {
    if (!ready.value && !sourceMode.value) return;
    while (queuedInsertions.length) {
        const request = queuedInsertions.shift()!;
        insertAttachment(request.file, request.image);
    }
}

// The editor is loaded asynchronously. An insertion request can arrive before
// Crepe is mounted: queue it and replay it once the editor is initialized.
watch(() => props.insertRequests, requests => {
    for (const request of requests ?? []) {
        if (handledInsertions.has(request.id)) continue;
        handledInsertions.add(request.id);
        queuedInsertions.push(request);
    }
    flushQueuedInsertions();
}, { immediate: true });

type TextBlock = 'paragraph' | 'h1' | 'h2' | 'h3' | 'code';
type EditorFormatting = {
    block: TextBlock;
    bold: boolean;
    italic: boolean;
    bullet: boolean;
    ordered: boolean;
    link: boolean;
    table: boolean;
};
const formatting = ref<EditorFormatting>({
    block: 'paragraph', bold: false, italic: false,
    bullet: false, ordered: false, link: false, table: false,
});
// The first pointer interaction can land after Crepe's selectionUpdated event.
// Defer reading ProseMirror state until its DOM selection has settled; this
// also keeps toolbar state correct after click, keyboard movement and focus.
let formattingFrame: number | null = null;
function scheduleFormatting(): void {
    if (formattingFrame !== null) window.cancelAnimationFrame(formattingFrame);
    formattingFrame = window.requestAnimationFrame(() => {
        formattingFrame = null;
        syncFormatting();
    });
}
function attachSelectionListeners(): void {
    const element = root.value;
    if (!element) return;
    element.addEventListener('pointerup', scheduleFormatting);
    element.addEventListener('click', scheduleFormatting);
    element.addEventListener('keyup', scheduleFormatting);
    element.addEventListener('focusin', scheduleFormatting);
}
function detachSelectionListeners(): void {
    const element = root.value;
    if (element) {
        element.removeEventListener('pointerup', scheduleFormatting);
        element.removeEventListener('click', scheduleFormatting);
        element.removeEventListener('keyup', scheduleFormatting);
        element.removeEventListener('focusin', scheduleFormatting);
    }
    if (formattingFrame !== null) window.cancelAnimationFrame(formattingFrame);
    formattingFrame = null;
}

const currentBlockLabel = computed(() => {
    switch (formatting.value.block) {
        case 'h1': return t('visualEditor.heading1');
        case 'h2': return t('visualEditor.heading2');
        case 'h3': return t('visualEditor.heading3');
        case 'code': return t('visualEditor.codeBlock');
        default: return t('visualEditor.paragraph');
    }
});

// Read ProseMirror's current selection; don't infer active buttons from the
// last toolbar click. This also follows keyboard shortcuts and loaded Markdown.
function syncFormatting(): void {
    if (!crepe || !ready.value || sourceMode.value) return;
    crepe.editor.action(ctx => {
        const state = ctx.get(editorViewCtx).state;
        const { $from, from, to, empty } = state.selection;
        const schema = state.schema;
        let block: TextBlock = 'paragraph';
        let bullet = false;
        let ordered = false;
        let table = false;
        for (let depth = $from.depth; depth >= 0; depth--) {
            const node = $from.node(depth);
            if (node.type === schema.nodes.bullet_list) bullet = true;
            if (node.type === schema.nodes.ordered_list) ordered = true;
            if (node.type === schema.nodes.table) table = true;
            if (node.type === schema.nodes.code_block) block = 'code';
            if (node.type === schema.nodes.heading && block === 'paragraph') {
                const level = Number(node.attrs.level);
                if (level === 1 || level === 2 || level === 3) block = `h${level}` as TextBlock;
            }
        }
        // For a caret, storedMarks also covers a mark toggled before typing.
        const marks = empty ? (state.storedMarks ?? $from.marks()) : null;
        const hasMark = (type: typeof schema.marks.strong) => !!type && (
            empty ? !!marks?.some(mark => mark.type === type) : state.doc.rangeHasMark(from, to, type)
        );
        formatting.value = {
            block, bullet, ordered, table,
            bold: hasMark(schema.marks.strong),
            italic: hasMark(schema.marks.em),
            link: hasMark(schema.marks.link),
        };
    });
}
const baseUrl = computed(() => `/projects/${props.projectId}/tasks/${props.taskId}/attachments`);

function attachmentUrl(id: number, image: boolean): string {
    return `${baseUrl.value}/${id}/${image ? 'preview' : 'download'}`;
}

// Inside the visual editor, browser URLs are used for images. In the database
// the content always remains portable Markdown with attachment:ID references.
function toVisual(markdown: string): string {
    return markdown.replace(/(\]\()attachment:(\d+)(\))/gi, (whole, open: string, id: string, close: string) => {
        const file = allAttachments.value.find(a => a.id === Number(id));
        if (!file) return whole;
        return `${open}${attachmentUrl(file.id, file.is_image)}${close}`;
    });
}
function toPortable(markdown: string): string {
    const escapedProject = String(props.projectId);
    const escapedTask = String(props.taskId);
    const re = new RegExp(`(\\]\\()(?:https?:\\/\\/[^\\s)]+)?\\/projects\\/${escapedProject}\\/tasks\\/${escapedTask}\\/attachments\\/(\\d+)\\/(?:preview|download)(\\))`, 'g');
    return markdown.replace(re, '$1attachment:$2$3');
}

function csrfToken(): Record<string, string> {
    const cookie = document.cookie.split('; ').find(v => v.startsWith('XSRF-TOKEN='));
    if (cookie) return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice(11)) };
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (meta) return { 'X-CSRF-TOKEN': meta };
    throw new Error('CSRF token unavailable');
}
async function uploadImage(file: File): Promise<string> {
    if (!props.canUpload || props.disabled) throw new Error('Upload not permitted');
    activeUploads += 1;
    emit('uploading', true);
    try {
        const payload = new FormData();
        payload.append('file', file);
        const response = await fetch(`${baseUrl.value}/inline`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...csrfToken() },
            body: payload,
        });
        if (!response.ok) throw new Error(`Attachment upload failed: ${response.status}`);
        const data: { attachment: TaskAttachmentSummary } = await response.json();
        localUploads.value.push(data.attachment);
        emit('uploaded', data.attachment);
        return attachmentUrl(data.attachment.id, true);
    } catch (error) {
        toast.error(t('visualEditor.uploadError'));
        throw error;
    } finally {
        activeUploads -= 1;
        if (activeUploads === 0) emit('uploading', false);
    }
}

function setValue(value: string): void {
    if (value.length > props.maxLength) {
        toast.error(t('messages.markdown.tooLong'));
        return;
    }
    emit('update:modelValue', value);
}

async function createEditor(): Promise<void> {
    if (!root.value) return;
    const instance = new Crepe({
        root: root.value,
        defaultValue: toVisual(props.modelValue),
        featureConfigs: {
            [Crepe.Feature.Placeholder]: { text: props.placeholder, mode: 'block' },
            [Crepe.Feature.ImageBlock]: { onUpload: uploadImage },
            // Block insertion is handled by TaskFlow's shadcn-vue menu.
            // Crepe's built-in block menu is too wide for the task panel.
        },
        features: {
            // Rendering existing images must not depend on upload permission.
            [Crepe.Feature.ImageBlock]: true,
            [Crepe.Feature.BlockEdit]: false,
            [Crepe.Feature.Toolbar]: false,
            [Crepe.Feature.Latex]: false,
            [Crepe.Feature.TopBar]: false,
        },
    });
    crepe = instance;
    instance.on(listener => {
        listener.markdownUpdated((_ctx, markdown) => {
            if (!updatingFromSource && !sourceMode.value) setValue(toPortable(markdown));
        });
        listener.selectionUpdated(scheduleFormatting);
        listener.updated(scheduleFormatting);
        listener.focus(scheduleFormatting);
    });
    try {
        await instance.create();
        instance.setReadonly(props.disabled);
        ready.value = true;
        flushQueuedInsertions();
        attachSelectionListeners();
        scheduleFormatting();
    } catch {
        // Keep the source editor available if the rich editor cannot initialize.
        sourceMode.value = true;
        flushQueuedInsertions();
        toast.error(t('visualEditor.loadError'));
    }
}

onMounted(() => { void createEditor(); });
onBeforeUnmount(() => { detachSelectionListeners(); crepe?.destroy(); crepe = null; });
watch(() => props.disabled, disabled => crepe?.setReadonly(disabled));

function toggleSource(): void {
    if (sourceMode.value) {
        sourceMode.value = false;
        updatingFromSource = true;
        crepe?.editor.action(replaceAll(toVisual(props.modelValue), true));
        nextTick(() => { updatingFromSource = false; syncFormatting(); });
    } else {
        if (crepe) setValue(toPortable(crepe.getMarkdown()));
        sourceMode.value = true;
    }
}

// TaskFlow's compact toolbar works directly with the same ProseMirror editor
// as Crepe. There is only one editor state, one Markdown source and no parallel
// contentEditable surface to become out of sync.
type BlockKind = 'paragraph' | 'h1' | 'h2' | 'h3' | 'bullet' | 'ordered' | 'quote' | 'code' | 'rule' | 'table';

function formatHeading(level: number | null): void {
    if (props.disabled || sourceMode.value || !crepe) return;
    crepe.editor.action(ctx => {
        const view = ctx.get(editorViewCtx);
        const type = level === null ? view.state.schema.nodes.paragraph : view.state.schema.nodes.heading;
        if (!type) return;
        setBlockType(type, level === null ? undefined : { level })(view.state, view.dispatch, view);
        view.focus();
    });
    syncFormatting();
}

function formatMark(kind: 'strong' | 'em'): void {
    if (props.disabled || sourceMode.value || !crepe) return;
    crepe.editor.action(ctx => {
        const view = ctx.get(editorViewCtx);
        const type = view.state.schema.marks[kind];
        if (!type) return;
        toggleMark(type)(view.state, view.dispatch, view);
        view.focus();
    });
    syncFormatting();
}

function formatList(ordered: boolean): void {
    if (props.disabled || sourceMode.value || !crepe) return;
    crepe.editor.action(ctx => {
        const view = ctx.get(editorViewCtx);
        const schema = view.state.schema;
        const type = schema.nodes[ordered ? 'ordered_list' : 'bullet_list'];
        const item = schema.nodes.list_item;
        if (!type || !item) return;
        const $from = view.state.selection.$from;
        for (let depth = $from.depth; depth > 0; depth--) {
            const ancestor = $from.node(depth);
            if (ancestor.type === type) {
                liftListItem(item)(view.state, view.dispatch, view);
                view.focus();
                return;
            }
            if (ancestor.type === schema.nodes.bullet_list || ancestor.type === schema.nodes.ordered_list) {
                view.dispatch(view.state.tr.setNodeMarkup($from.before(depth), type));
                view.focus();
                return;
            }
        }
        wrapInList(type)(view.state, view.dispatch, view);
        view.focus();
    });
    syncFormatting();
}

function insertNewBlock(kind: BlockKind): void {
    if (props.disabled || sourceMode.value || !ready.value || !crepe) return;
    crepe.editor.action(ctx => {
        const view = ctx.get(editorViewCtx);
        const schema = view.state.schema;
        const paragraph = schema.nodes.paragraph;
        if (!paragraph) return;
        const blank = () => paragraph.create();
        let block = blank();
        if (kind === 'h1' || kind === 'h2' || kind === 'h3') {
            block = schema.nodes.heading?.create({ level: Number(kind[1]) }) ?? block;
        } else if (kind === 'bullet' || kind === 'ordered') {
            const list = schema.nodes[kind === 'bullet' ? 'bullet_list' : 'ordered_list'];
            const item = schema.nodes.list_item;
            if (list && item) block = list.create(null, [item.create(null, blank())]);
        } else if (kind === 'quote') {
            block = schema.nodes.blockquote?.create(null, blank()) ?? block;
        } else if (kind === 'code') {
            block = schema.nodes.code_block?.create() ?? block;
        } else if (kind === 'rule') {
            block = schema.nodes.hr?.create() ?? schema.nodes.horizontal_rule?.create() ?? block;
        } else if (kind === 'table') {
            const table = schema.nodes.table;
            const row = schema.nodes.table_row;
            const cell = schema.nodes.table_cell;
            const header = schema.nodes.table_header ?? cell;
            const headerRow = schema.nodes.table_header_row ?? row;
            if (table && row && cell && header && headerRow) {
                block = table.create(null, [
                    headerRow.create(null, [header.create(null, blank()), header.create(null, blank())]),
                    row.create(null, [cell.create(null, blank()), cell.create(null, blank())]),
                ]);
            }
        }
        // Insert after the top-level block (not inside a list item or image).
        const $from = view.state.selection.$from;
        const position = $from.depth > 0 ? $from.after(1) : view.state.doc.content.size;
        const content = kind === 'rule' ? [block, blank()] : [block];
        const tr = view.state.tr.insert(position, content);
        const cursor = kind === 'rule' ? position + block.nodeSize + 1 : Math.min(position + (kind === 'bullet' || kind === 'ordered' || kind === 'table' ? 3 : kind === 'quote' ? 2 : 1), tr.doc.content.size);
        tr.setSelection(TextSelection.near(tr.doc.resolve(Math.min(cursor, tr.doc.content.size))));
        view.dispatch(tr.scrollIntoView());
        view.focus();
    });
    syncFormatting();
}

function addLink(): void {
    if (props.disabled || sourceMode.value || !crepe) return;
    const input = window.prompt(t('visualEditor.linkPrompt'), 'https://');
    if (!input) return;
    let href: string;
    try {
        const url = new URL(input);
        if (url.protocol !== 'http:' && url.protocol !== 'https:') throw new Error('Invalid scheme');
        href = url.href;
    } catch {
        toast.error(t('visualEditor.invalidLink'));
        return;
    }
    crepe.editor.action(ctx => {
        const view = ctx.get(editorViewCtx);
        const link = view.state.schema.marks.link;
        if (!link) return;
        const { from, to, empty } = view.state.selection;
        const tr = empty
            ? view.state.tr.replaceSelectionWith(view.state.schema.text(input, [link.create({ href })]))
            : view.state.tr.addMark(from, to, link.create({ href }));
        view.dispatch(tr.scrollIntoView());
        view.focus();
    });
    syncFormatting();
}

async function pickImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || !props.canUpload || props.disabled || uploadingImage.value) return;
    uploadingImage.value = true;
    try {
        const url = await uploadImage(file);
        const attachment = localUploads.value.find(item => attachmentUrl(item.id, true) === url);
        if (attachment) insertAttachment(attachment, true);
    } catch {
        // uploadImage already reports the error.
    } finally {
        uploadingImage.value = false;
    }
}

// Inserting directly-created ProseMirror nodes was fragile: Crepe's image
// block and link serializers could turn an attachment into plain text.
// Feed the editor proper Markdown through Milkdown's parser instead, then
// convert the same-task guarded URL back to attachment:ID before saving.
function insertAttachment(file: TaskAttachmentSummary, image: boolean): void {
    if (props.disabled) return;
    const asImage = image && file.is_image;
    const safeName = file.name.replace(/[\r\n]+/g, ' ').replace(/([\\[\]])/g, '\\$1');
    const source = `${asImage ? '!' : ''}[${safeName}](attachment:${file.id})`;

    if (sourceMode.value || !ready.value || !crepe) {
        setValue(`${props.modelValue}${props.modelValue && !props.modelValue.endsWith('\n') ? '\n' : ''}${asImage ? '\n' : ''}${source}${asImage ? '\n' : ''}\n`);
        return;
    }

    const browserMarkdown = `${asImage ? '!' : ''}[${safeName}](${attachmentUrl(file.id, asImage)})`;
    const before = toPortable(crepe.getMarkdown());
    const reference = `](attachment:${file.id})`;
    const occurrences = (markdown: string): number => markdown.split(reference).length - 1;

    if (asImage) {
        // An image is a block, not inline text. insertPos() can throw when
        // the current selection is inside an image, list or table; this used
        // to make "Insert image" silently do nothing while links worked.
        // Reparse the complete Markdown with a new top-level image block.
        const withImage = `${before.trimEnd()}${before.trim() ? '\n\n' : ''}${source}\n`;
        if (withImage.length > props.maxLength) {
            toast.error(t('messages.markdown.tooLong'));
            return;
        }
        try {
            crepe.editor.action(replaceAll(toVisual(withImage), true));
        } catch {
            // Keep the newly inserted image and all previous content editable
            // even if the visual parser encounters an unsupported block.
            sourceMode.value = true;
            setValue(withImage);
            toast.error(t('visualEditor.loadError'));
            return;
        }
    } else {
        // A parsed Markdown link retains its href across editor transactions.
        crepe.editor.action(insert(browserMarkdown, true));
    }

    let result = toPortable(crepe.getMarkdown());
    if (occurrences(result) <= occurrences(before)) {
        // Preserve the reference if a plugin stripped the image or link.
        const preserved = `${before.trimEnd()}${before.trim() ? '\n\n' : ''}${source}\n`;
        if (preserved.length > props.maxLength) {
            toast.error(t('messages.markdown.tooLong'));
            return;
        }
        try {
            crepe.editor.action(replaceAll(toVisual(preserved), true));
            result = toPortable(crepe.getMarkdown());
        } catch {
            // The source editor is always available as a safe fallback.
            result = before;
        }
        if (occurrences(result) <= occurrences(before)) {
            sourceMode.value = true;
            setValue(preserved);
            toast.error(t('visualEditor.loadError'));
            return;
        }
    }
    setValue(result);
}

defineExpose({ insertAttachment });
</script>

<template>
    <div class="visual-markdown-editor relative rounded-md border border-input" :class="{ 'is-compact': compact }" :aria-label="t('visualEditor.label')">
        <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-t-md border-b bg-muted/20 px-2 py-1.5">
            <span class="text-xs text-muted-foreground">{{ t('visualEditor.label') }}</span>
            <div class="flex items-center gap-1">
                <DropdownMenu v-if="!sourceMode && !disabled">
                    <DropdownMenuTrigger as-child>
                        <Button type="button" variant="ghost" size="sm" :disabled="!ready" class="gap-1.5">
                            <Plus class="size-3.5" /> {{ t('visualEditor.addBlock') }} <ChevronDown class="size-3" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem @select="insertNewBlock('paragraph')">{{ t('visualEditor.paragraph') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('h1')"><Heading1 class="size-4" />{{ t('visualEditor.heading1') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('h2')"><Heading2 class="size-4" />{{ t('visualEditor.heading2') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('h3')"><Heading3 class="size-4" />{{ t('visualEditor.heading3') }}</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @select="insertNewBlock('bullet')"><List class="size-4" />{{ t('visualEditor.bulletList') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('ordered')"><ListOrdered class="size-4" />{{ t('visualEditor.numberedList') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('quote')"><Quote class="size-4" />{{ t('visualEditor.quote') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('code')"><Code2 class="size-4" />{{ t('visualEditor.codeBlock') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('table')"><Table2 class="size-4" />{{ t('visualEditor.table') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="insertNewBlock('rule')"><Minus class="size-4" />{{ t('visualEditor.divider') }}</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button type="button" variant="ghost" size="sm" :disabled="disabled || !ready" @click="toggleSource">
                    {{ sourceMode ? t('visualEditor.visual') : t('visualEditor.source') }}
                </Button>
            </div>
        </div>
        <div v-if="!sourceMode" role="toolbar" :aria-label="t('visualEditor.formatting')" class="flex min-w-0 items-center gap-0.5 overflow-x-auto border-b px-2 py-1" :class="{ 'opacity-50': disabled || !ready }">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" :disabled="disabled || !ready" class="shrink-0 gap-1 px-2" :aria-label="currentBlockLabel">
                        {{ currentBlockLabel }} <ChevronDown class="size-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-44">
                    <DropdownMenuItem :class="{ 'bg-accent': formatting.block === 'paragraph' }" @select="formatHeading(null)">{{ t('visualEditor.paragraph') }}<Check v-if="formatting.block === 'paragraph'" class="ml-auto size-3.5" /></DropdownMenuItem>
                    <DropdownMenuItem :class="{ 'bg-accent': formatting.block === 'h1' }" @select="formatHeading(1)"><Heading1 class="size-4" />{{ t('visualEditor.heading1') }}<Check v-if="formatting.block === 'h1'" class="ml-auto size-3.5" /></DropdownMenuItem>
                    <DropdownMenuItem :class="{ 'bg-accent': formatting.block === 'h2' }" @select="formatHeading(2)"><Heading2 class="size-4" />{{ t('visualEditor.heading2') }}<Check v-if="formatting.block === 'h2'" class="ml-auto size-3.5" /></DropdownMenuItem>
                    <DropdownMenuItem :class="{ 'bg-accent': formatting.block === 'h3' }" @select="formatHeading(3)"><Heading3 class="size-4" />{{ t('visualEditor.heading3') }}<Check v-if="formatting.block === 'h3'" class="ml-auto size-3.5" /></DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <div class="mx-1 h-5 shrink-0 border-l" />
            <Button type="button" :variant="formatting.bold ? 'secondary' : 'ghost'" :aria-pressed="formatting.bold" size="icon-sm" class="shrink-0" :title="t('visualEditor.bold')" :aria-label="t('visualEditor.bold')" :disabled="disabled || !ready" @mousedown.prevent @click="formatMark('strong')"><Bold class="size-4" /></Button>
            <Button type="button" :variant="formatting.italic ? 'secondary' : 'ghost'" :aria-pressed="formatting.italic" size="icon-sm" class="shrink-0" :title="t('visualEditor.italic')" :aria-label="t('visualEditor.italic')" :disabled="disabled || !ready" @mousedown.prevent @click="formatMark('em')"><Italic class="size-4" /></Button>
            <div class="mx-1 h-5 shrink-0 border-l" />
            <Button type="button" :variant="formatting.bullet ? 'secondary' : 'ghost'" :aria-pressed="formatting.bullet" size="icon-sm" class="shrink-0" :title="t('visualEditor.bulletList')" :aria-label="t('visualEditor.bulletList')" :disabled="disabled || !ready" @mousedown.prevent @click="formatList(false)"><List class="size-4" /></Button>
            <Button type="button" :variant="formatting.ordered ? 'secondary' : 'ghost'" :aria-pressed="formatting.ordered" size="icon-sm" class="shrink-0" :title="t('visualEditor.numberedList')" :aria-label="t('visualEditor.numberedList')" :disabled="disabled || !ready" @mousedown.prevent @click="formatList(true)"><ListOrdered class="size-4" /></Button>
            <Button type="button" :variant="formatting.block === 'code' ? 'secondary' : 'ghost'" :aria-pressed="formatting.block === 'code'" size="icon-sm" class="shrink-0" :title="t('visualEditor.codeBlock')" :aria-label="t('visualEditor.codeBlock')" :disabled="disabled || !ready" @mousedown.prevent @click="insertNewBlock('code')"><Code2 class="size-4" /></Button>
            <div class="mx-1 h-5 shrink-0 border-l" />
            <Button type="button" :variant="formatting.link ? 'secondary' : 'ghost'" :aria-pressed="formatting.link" size="icon-sm" class="shrink-0" :title="t('visualEditor.link')" :aria-label="t('visualEditor.link')" :disabled="disabled || !ready" @click="addLink"><Link2 class="size-4" /></Button>
            <input ref="imagePicker" type="file" class="sr-only" accept=".png,.jpg,.jpeg,.webp" :aria-label="t('visualEditor.image')" @change="pickImage" />
            <Button v-if="canUpload" type="button" variant="ghost" size="icon-sm" class="shrink-0" :title="t('visualEditor.image')" :aria-label="t('visualEditor.image')" :disabled="disabled || !ready || uploadingImage" @click="imagePicker?.click()"><ImageIcon class="size-4" /></Button>
            <Button type="button" :variant="formatting.table ? 'secondary' : 'ghost'" :aria-pressed="formatting.table" size="icon-sm" class="shrink-0" :title="t('visualEditor.table')" :aria-label="t('visualEditor.table')" :disabled="disabled || !ready" @click="insertNewBlock('table')"><Table2 class="size-4" /></Button>
        </div>
        <div ref="root" v-show="!sourceMode" class="visual-markdown-body" />
        <textarea v-if="sourceMode" :value="modelValue" :disabled="disabled" :maxlength="maxLength"
            rows="8" class="block w-full resize-y bg-transparent px-3 py-2 font-mono text-sm outline-none"
            @input="setValue(($event.target as HTMLTextAreaElement).value)" />
    </div>
</template>

<style scoped>
/* Keep Crepe's mechanics, but let the shared task Markdown stylesheet own
   text typography in BOTH the visual editor and the saved preview. */
.visual-markdown-editor :deep(.milkdown) {
    --crepe-base-font-size: 14px;
    --crepe-font-title: var(--font-sans, 'Instrument Sans', ui-sans-serif, sans-serif);
    --crepe-font-default: var(--font-sans, 'Instrument Sans', ui-sans-serif, sans-serif);
    --crepe-font-code: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    --crepe-color-background: var(--background);
    --crepe-color-on-background: var(--foreground);
    --crepe-color-outline: var(--border);
    --crepe-color-surface: var(--card);
    --crepe-color-on-surface: var(--card-foreground);
    background: transparent;
    color: var(--foreground);
    font-family: var(--crepe-font-default);
}
.visual-markdown-editor :deep(.milkdown .editor) {
    min-height: 7rem;
    max-width: none;
    padding: 0.75rem 1rem 0.875rem;
    overflow: visible;
}
/* A message starts compact, but content grows naturally with the cursor.
   The description editor retains its existing size and formatting. */
.visual-markdown-editor.is-compact :deep(.milkdown .editor) {
    min-height: 11rem;
}
@media (max-width: 540px) {
    .visual-markdown-editor :deep(.milkdown .editor) {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}
</style>
