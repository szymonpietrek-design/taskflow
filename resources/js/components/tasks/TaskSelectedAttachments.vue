<script setup lang="ts">
import { Image as ImageIcon, Paperclip, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import type { TaskAttachmentSummary } from '@/types/task';

withDefaults(defineProps<{
    files: TaskAttachmentSummary[];
    disabled?: boolean;
}>(), { disabled: false });

const emit = defineEmits<{ remove: [id: number] }>();
const { t } = useI18n();
</script>

<template>
    <div v-if="files.length" class="flex flex-wrap gap-1.5" :aria-label="t('messages.attachments.selected')">
        <span
            v-for="file in files"
            :key="file.id"
            class="inline-flex max-w-full items-center gap-1 rounded-md border bg-muted/25 py-0.5 pl-2 pr-0.5 text-xs"
        >
            <ImageIcon v-if="file.is_image" class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
            <Paperclip v-else class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
            <span class="max-w-52 truncate" :title="file.name">{{ file.name }}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="size-6 shrink-0 text-muted-foreground hover:text-destructive"
                :disabled="disabled"
                :aria-label="`${t('messages.attachments.unlink')} — ${file.name}`"
                @click="emit('remove', file.id)"
            >
                <X class="size-3.5" aria-hidden="true" />
            </Button>
        </span>
    </div>
</template>
