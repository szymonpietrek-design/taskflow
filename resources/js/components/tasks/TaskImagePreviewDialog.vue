<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { TaskAttachmentSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    taskId: number;
    file: TaskAttachmentSummary | null;
}>();
const emit = defineEmits<{ close: [] }>();
const { t } = useI18n();
const base = computed(() => `/projects/${props.projectId}/tasks/${props.taskId}/attachments/${props.file?.id}`);
</script>

<template>
    <Dialog :open="file !== null" @update:open="(open) => { if (!open) emit('close'); }">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
            <DialogHeader>
                <DialogTitle class="break-words pr-6">{{ file?.name }}</DialogTitle>
            </DialogHeader>
            <img
                v-if="file?.is_image"
                :src="`${base}/preview`"
                :alt="file.name"
                class="mx-auto max-h-[70vh] max-w-full rounded-md object-contain"
            />
            <a
                v-if="file"
                :href="`${base}/download`"
                class="inline-flex w-fit items-center gap-2 text-sm underline underline-offset-4 hover:text-primary"
            >
                <Download class="size-4" /> {{ t('attachments.download_image') }}
            </a>
        </DialogContent>
    </Dialog>
</template>
