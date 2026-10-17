<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { router } from '@inertiajs/vue3';
import { Download, FileArchive, FileJson, FileText, Image as ImageIcon, LoaderCircle, Paperclip, Plus, Trash2, CloudUpload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { TaskAttachmentSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    taskId: number;
    attachments: TaskAttachmentSummary[];
    canUpdate: boolean;
}>();

const { t, formatText, locale } = useI18n();
const base = computed(() => `/projects/${props.projectId}/tasks/${props.taskId}/attachments`);
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFiles = ref<File[]>([]);
const uploadError = ref('');
const uploading = ref(false);
const deleting = ref(false);
const pendingDelete = ref<TaskAttachmentSummary | null>(null);
const imagePreview = ref<TaskAttachmentSummary | null>(null);

watch(() => props.taskId, () => {
    selectedFiles.value = [];
    pendingDelete.value = null;
    imagePreview.value = null;
    uploadError.value = '';
});

function previewUrl(item: TaskAttachmentSummary): string {
    return `${base.value}/${item.id}/preview`;
}
function downloadUrl(item: TaskAttachmentSummary): string {
    return `${base.value}/${item.id}/download`;
}
function sizeLabel(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toLocaleString(locale.value === 'pl' ? 'pl-PL' : 'en-US', { maximumFractionDigits: 1 })} MB`;
}
function dateLabel(date: string | null): string {
    if (!date) return '';
    return new Date(date).toLocaleDateString(locale.value === 'pl' ? 'pl-PL' : 'en-US');
}
function chooseFiles(files: FileList | File[]): void {
    const incoming = Array.from(files);
    uploadError.value = '';
    if (incoming.length > 5 || incoming.some(file => file.size > 20 * 1024 * 1024)) {
        uploadError.value = t('attachments.limits');
        return;
    }
    selectedFiles.value = incoming;
}
function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files) chooseFiles(input.files);
}
function onDrop(event: DragEvent): void {
    if (!props.canUpdate || uploading.value || !event.dataTransfer?.files) return;
    chooseFiles(event.dataTransfer.files);
}
function upload(): void {
    if (!props.canUpdate || uploading.value || !selectedFiles.value.length) return;
    uploadError.value = '';
    router.post(base.value, { files: selectedFiles.value }, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => { uploading.value = true; },
        onFinish: () => { uploading.value = false; },
        onSuccess: () => {
            selectedFiles.value = [];
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: (errors) => {
            uploadError.value = String(errors.files || Object.entries(errors).find(([key]) => key.startsWith('files.'))?.[1] || t('attachments.upload_error'));
        },
    });
}
function removeAttachment(): void {
    if (!props.canUpdate || !pendingDelete.value || deleting.value) return;
    deleting.value = true;
    router.delete(`${base.value}/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
        onError: (errors) => toast.error(String(errors.attachment || t('attachments.remove_error'))),
        onFinish: () => { deleting.value = false; },
    });
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between gap-3">
            <CardTitle class="flex items-center gap-2">
                <Paperclip class="size-4" />
                {{ t('attachments.title') }} ({{ attachments.length }})
            </CardTitle>
            <Button v-if="canUpdate" type="button" variant="outline" size="sm" :disabled="uploading" @click="fileInput?.click()">
                <Plus class="size-4" /> {{ t('attachments.add') }}
            </Button>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="attachments.length" class="grid gap-3 sm:grid-cols-2">
                <article v-for="item in attachments" :key="item.id" class="group min-w-0 overflow-hidden rounded-lg border bg-card">
                    <button
                        v-if="item.is_image"
                        type="button"
                        class="block w-full cursor-zoom-in overflow-hidden bg-muted focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring"
                        :aria-label="formatText('attachments.preview', { name: item.name })"
                        @click="imagePreview = item"
                    >
                        <img :src="previewUrl(item)" :alt="item.name" loading="lazy" class="aspect-video w-full object-cover transition-transform group-hover:scale-[1.02]" />
                    </button>
                    <div v-else class="flex aspect-[3/1] items-center justify-center bg-muted/40">
                        <FileArchive v-if="item.mime_type.includes('zip')" class="size-8 text-muted-foreground" />
                        <FileJson v-else-if="item.mime_type.includes('json')" class="size-8 text-muted-foreground" />
                        <FileText v-else class="size-8 text-muted-foreground" />
                    </div>
                    <div class="flex items-start gap-2 p-3">
                        <ImageIcon v-if="item.is_image" class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <Paperclip v-else class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" :title="item.name">{{ item.name }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ sizeLabel(item.size) }}<span v-if="item.uploader"> · {{ item.uploader }}</span><span v-if="item.created_at"> · {{ dateLabel(item.created_at) }}</span></p>
                        </div>
                        <a :href="downloadUrl(item)" class="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" :aria-label="formatText('attachments.download', { name: item.name })">
                            <Download class="size-4" />
                        </a>
                        <button v-if="canUpdate" type="button" class="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:outline-2 focus-visible:outline-ring" :aria-label="formatText('attachments.remove', { name: item.name })" @click="pendingDelete = item">
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">{{ t('attachments.empty') }}</p>

            <div v-if="canUpdate" class="space-y-3">
                <input ref="fileInput" type="file" multiple class="sr-only" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.json,.zip" :aria-label="t('attachments.select')" @change="onFileChange" />
                <div
                    class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground transition-colors hover:border-foreground/30"
                    @dragover.prevent
                    @drop.prevent="onDrop"
                >
                    <CloudUpload class="mx-auto mb-2 size-5" />
                    <p>{{ t('attachments.drop') }}</p>
                    <p class="mt-1 text-xs">{{ t('attachments.limits') }}</p>
                </div>
                <div v-if="selectedFiles.length" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/40 p-2">
                    <p class="min-w-0 truncate text-xs">{{ formatText('attachments.selected', { count: selectedFiles.length }) }}: {{ selectedFiles.map(f => f.name).join(', ') }}</p>
                    <Button type="button" size="sm" :disabled="uploading" @click="upload">
                        <LoaderCircle v-if="uploading" class="size-4 animate-spin" />
                        <Plus v-else class="size-4" />
                        {{ uploading ? t('attachments.uploading') : t('attachments.upload') }}
                    </Button>
                </div>
                <p v-if="uploadError" role="alert" class="text-sm text-destructive">{{ uploadError }}</p>
            </div>
        </CardContent>
    </Card>

    <Dialog :open="imagePreview !== null" @update:open="(open) => { if (!open) imagePreview = null; }">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
            <DialogHeader><DialogTitle class="break-all">{{ imagePreview?.name }}</DialogTitle></DialogHeader>
            <img v-if="imagePreview" :src="previewUrl(imagePreview)" :alt="imagePreview.name" class="mx-auto max-h-[70vh] max-w-full rounded-md object-contain" />
            <a v-if="imagePreview" :href="downloadUrl(imagePreview)" class="inline-flex items-center gap-2 text-sm underline underline-offset-4"><Download class="size-4" /> {{ t('attachments.download_image') }}</a>
        </DialogContent>
    </Dialog>

    <Dialog :open="pendingDelete !== null" @update:open="(open) => { if (!open && !deleting) pendingDelete = null; }">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('attachments.remove_title') }}</DialogTitle>
                <DialogDescription>{{ formatText('attachments.remove_confirm', { name: pendingDelete?.name ?? '' }) }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-3 sm:gap-3">
                <Button variant="outline" type="button" :disabled="deleting" @click="pendingDelete = null">{{ t('attachments.cancel') }}</Button>
                <Button variant="destructive" type="button" :disabled="deleting" @click="removeAttachment">
                    <LoaderCircle v-if="deleting" class="size-4 animate-spin" /><Trash2 v-else class="size-4" />{{ t('attachments.delete') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
