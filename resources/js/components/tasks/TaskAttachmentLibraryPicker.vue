<script setup lang="ts">
import { computed, ref } from 'vue';
import { Image as ImageIcon, Link2, Paperclip, Search, ZoomIn } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import TaskImagePreviewDialog from '@/components/tasks/TaskImagePreviewDialog.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { TaskAttachmentSummary } from '@/types/task';

const props = withDefaults(defineProps<{
    attachments: TaskAttachmentSummary[];
    selectedIds: number[];
    editing?: boolean;
    disabled?: boolean;
    maxFiles?: number;
    scope: string;
    projectId: number;
    taskId: number;
    selectable?: boolean;
    titleKey?: string;
    helpKey?: string;
}>(), {
    editing: false,
    disabled: false,
    maxFiles: 20,
    selectable: true,
    titleKey: 'messages.attachments.libraryTitle',
    helpKey: undefined,
});

const emit = defineEmits<{
    toggle: [id: number, checked: boolean];
    insert: [file: TaskAttachmentSummary, image: boolean];
}>();

const { t, locale, formatText } = useI18n();
const query = ref('');
const previewFile = ref<TaskAttachmentSummary | null>(null);
const totalSelected = computed(() => props.selectedIds.length);
const resolvedHelpKey = computed(() => props.helpKey ?? (props.editing
    ? 'messages.attachments.editLibraryHelp'
    : 'messages.attachments.libraryHelp'));
const filteredAttachments = computed(() => {
    const term = query.value.trim().toLocaleLowerCase();
    return term
        ? props.attachments.filter(file => file.name.toLocaleLowerCase().includes(term))
        : props.attachments;
});
const isSelected = (id: number) => props.selectedIds.includes(id);
const limitReached = (id: number) => !isSelected(id) && totalSelected.value >= props.maxFiles;
const selectionDisabled = (id: number) => props.disabled || (props.selectable && limitReached(id));
const insertDisabled = (id: number) => props.disabled || (props.selectable && limitReached(id));

function toggle(id: number, checked: boolean): void {
    if (!props.selectable || selectionDisabled(id)) return;
    emit('toggle', id, checked);
}

function formatSize(bytes: number): string {
    const unit = bytes >= 1024 * 1024 ? 'MB' : bytes >= 1024 ? 'KB' : 'B';
    const divisor = unit === 'MB' ? 1024 * 1024 : unit === 'KB' ? 1024 : 1;
    return `${new Intl.NumberFormat(locale.value === 'pl' ? 'pl-PL' : 'en-US', {
        maximumFractionDigits: unit === 'B' ? 0 : 1,
    }).format(bytes / divisor)} ${unit}`;
}

function fileType(file: TaskAttachmentSummary): string {
    const extension = file.name.split('.').pop();
    return extension && extension !== file.name && extension.length <= 8
        ? extension.toUpperCase()
        : file.is_image ? 'IMAGE' : 'FILE';
}
</script>

<template>
    <section class="space-y-3 rounded-lg border bg-muted/10 p-3" :aria-label="t('messages.attachments.fromTask')">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h4 class="text-sm font-medium">{{ t(titleKey) }}</h4>
                <p class="mt-0.5 text-xs leading-5 text-muted-foreground">
                    {{ t(resolvedHelpKey) }}
                </p>
            </div>
            <span v-if="selectable" class="shrink-0 rounded-md border bg-background px-2 py-1 text-xs tabular-nums text-muted-foreground">
                {{ t('messages.attachments.selected') }}: {{ totalSelected }} / {{ maxFiles }}
            </span>
        </div>

        <div v-if="attachments.length > 5" class="relative">
            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
            <Input v-model="query" type="search" class="h-8 pl-9 text-sm" :placeholder="t('messages.attachments.search')" :aria-label="t('messages.attachments.search')" />
        </div>

        <div v-if="attachments.length" class="max-h-72 divide-y overflow-y-auto rounded-md border bg-background" role="group" :aria-label="t('messages.attachments.libraryTitle')">
            <div
                v-for="file in filteredAttachments"
                :key="file.id"
                class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 px-2.5 py-2 transition-colors"
                :class="[
                    selectable && !selectionDisabled(file.id) ? 'cursor-pointer' : 'cursor-default',
                    selectable && isSelected(file.id) ? 'bg-muted/60' : 'hover:bg-muted/30',
                ]"
                @click="toggle(file.id, !isSelected(file.id))"
            >
                <div class="flex min-w-0 flex-[1_1_220px] items-center gap-2.5">
                    <span v-if="selectable" class="flex shrink-0 items-center" @click.stop>
                        <Checkbox
                            :id="`message-attachment-${scope}-${file.id}`"
                            :model-value="isSelected(file.id)"
                            :disabled="selectionDisabled(file.id)"
                            :aria-label="file.name"
                            @update:model-value="toggle(file.id, $event === true)"
                        />
                    </span>
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md border bg-muted/30" aria-hidden="true">
                        <ImageIcon v-if="file.is_image" class="size-4 text-muted-foreground" />
                        <Paperclip v-else class="size-4 text-muted-foreground" />
                    </span>
                    <div
                        class="min-w-0 flex-1 select-none"
                        :class="selectable && !selectionDisabled(file.id) ? 'cursor-pointer' : 'cursor-default'"
                        @click.prevent.stop="toggle(file.id, !isSelected(file.id))"
                    >
                        <span class="block truncate text-sm font-medium" :title="file.name">{{ file.name }}</span>
                        <span class="block text-xs text-muted-foreground">{{ fileType(file) }} · {{ formatSize(file.size) }}</span>
                    </div>
                </div>
                <TooltipProvider :delay-duration="200">
                    <div class="ml-auto flex shrink-0 items-center gap-1 pl-5 sm:pl-0">
                        <Tooltip v-if="file.is_image">
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="ghost"
                                    class="size-9 text-muted-foreground hover:text-foreground"
                                    :aria-label="formatText('attachments.preview', { name: file.name })"
                                    @click.stop="previewFile = file"
                                >
                                    <ZoomIn class="size-4" aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">{{ formatText('attachments.preview', { name: file.name }) }}</TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="file.is_image">
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="ghost"
                                    class="size-9 text-muted-foreground hover:text-foreground"
                                    :disabled="insertDisabled(file.id)"
                                    :aria-label="`${t('messages.markdown.insertImageShort')} — ${file.name}`"
                                    @click.stop="emit('insert', file, true)"
                                >
                                    <ImageIcon class="size-4" aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">{{ t('messages.markdown.insertImageShort') }}</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="ghost"
                                    class="size-9 text-muted-foreground hover:text-foreground"
                                    :disabled="insertDisabled(file.id)"
                                    :aria-label="`${t('messages.markdown.insertLinkShort')} — ${file.name}`"
                                    @click.stop="emit('insert', file, false)"
                                >
                                    <Link2 class="size-4" aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">{{ t('messages.markdown.insertLinkShort') }}</TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>
            <p v-if="!filteredAttachments.length" class="px-3 py-5 text-center text-sm text-muted-foreground">{{ t('messages.attachments.noSearchResults') }}</p>
        </div>
        <p v-else class="py-2 text-sm text-muted-foreground">{{ t('attachments.empty') }}</p>
        <p v-if="selectable && totalSelected >= maxFiles" role="status" class="text-xs text-muted-foreground">{{ t('messages.markdown.maxFiles') }}</p>
    </section>

    <TaskImagePreviewDialog
        :project-id="projectId"
        :task-id="taskId"
        :file="previewFile"
        @close="previewFile = null"
    />
</template>
