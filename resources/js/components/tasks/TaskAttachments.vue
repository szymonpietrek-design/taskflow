<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { router } from '@inertiajs/vue3';
import { Download, FileArchive, FileJson, FileText, LoaderCircle, Paperclip, Plus, Trash2, CloudUpload, X, ZoomIn } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { TaskAttachmentSummary } from '@/types/task';
import TaskImagePreviewDialog from '@/components/tasks/TaskImagePreviewDialog.vue';

const props = defineProps<{
    projectId: number;
    taskId: number;
    attachments: TaskAttachmentSummary[];
    embeddedAttachmentIds?: number[];
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
const showUploader = ref(false);
const embeddedIds = computed(() => new Set(props.embeddedAttachmentIds ?? []));

watch(() => props.taskId, () => {
    selectedFiles.value = [];
    pendingDelete.value = null;
    imagePreview.value = null;
    uploadError.value = '';
    showUploader.value = false;
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
    showUploader.value = true;
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
            showUploader.value = false;
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
                {{ t('attachments.title') }}
                <span class="text-sm font-normal text-muted-foreground">({{ attachments.length }})</span>
            </CardTitle>
            <Button
                v-if="canUpdate" type="button" variant="outline" size="sm"
                :disabled="uploading" :aria-expanded="showUploader"
                @click="showUploader = !showUploader"
            >
                <Plus v-if="!showUploader" class="size-4" />
                <X v-else class="size-4" />
                {{ showUploader ? t('attachments.cancel') : t('attachments.add') }}
            </Button>
        </CardHeader>
        <CardContent class="space-y-3">
            <div v-if="attachments.length" class="divide-y rounded-lg border">
                <article v-for="item in attachments" :key="item.id" class="flex min-w-0 items-center gap-3 px-3 py-2.5">
                    <button
                        v-if="item.is_image" type="button"
                        class="size-14 shrink-0 overflow-hidden rounded-md bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        :aria-label="formatText('attachments.preview', { name: item.name })"
                        @click="imagePreview = item"
                    >
                        <img :src="previewUrl(item)" :alt="item.name" loading="lazy" class="size-full object-cover" />
                    </button>
                    <div v-else class="flex size-14 shrink-0 items-center justify-center rounded-md bg-muted/50">
                        <FileArchive v-if="item.mime_type.includes('zip')" class="size-5 text-muted-foreground" />
                        <FileJson v-else-if="item.mime_type.includes('json')" class="size-5 text-muted-foreground" />
                        <FileText v-else class="size-5 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="truncate text-sm font-medium" :title="item.name">{{ item.name }}</p>
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                            <span>{{ sizeLabel(item.size) }}</span>
                            <span v-if="item.uploader">· {{ item.uploader }}</span>
                            <span v-if="item.created_at">· {{ dateLabel(item.created_at) }}</span>
                            <span
                                v-if="embeddedIds.has(item.id)"
                                class="rounded bg-muted px-1.5 py-0.5 text-[11px] text-foreground"
                            >{{ t('stage20.usedInDescription') }}</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            v-if="item.is_image" type="button"
                            class="rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                            :aria-label="formatText('attachments.preview', { name: item.name })"
                            :title="formatText('attachments.preview', { name: item.name })"
                            @click="imagePreview = item"
                        ><ZoomIn class="size-4" /></button>
                        <a
                            :href="downloadUrl(item)"
                            class="rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                            :aria-label="formatText('attachments.download', { name: item.name })"
                        ><Download class="size-4" /></a>
                        <button
                            v-if="canUpdate" type="button"
                            class="rounded-md p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:outline-2 focus-visible:outline-ring"
                            :aria-label="formatText('attachments.remove', { name: item.name })"
                            @click="pendingDelete = item"
                        ><Trash2 class="size-4" /></button>
                    </div>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">{{ t('attachments.empty') }}</p>

            <div v-if="canUpdate && showUploader" class="space-y-3 border-t pt-3">
                <input ref="fileInput" type="file" multiple class="sr-only" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.json,.zip" :aria-label="t('attachments.select')" @change="onFileChange" />
                <button
                    type="button"
                    class="flex w-full flex-col items-center rounded-lg border border-dashed px-4 py-5 text-center text-sm text-muted-foreground transition-colors hover:border-foreground/30 hover:bg-muted/20 focus-visible:outline-2 focus-visible:outline-ring"
                    :disabled="uploading"
                    @click="fileInput?.click()"
                    @dragover.prevent
                    @drop.prevent="onDrop"
                >
                    <CloudUpload class="mb-2 size-5" />
                    <span>{{ t('stage20.dropOrChoose') }}</span>
                    <span class="mt-1 text-xs">{{ t('attachments.limits') }}</span>
                </button>
                <div v-if="selectedFiles.length" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/40 p-2">
                    <p class="min-w-0 truncate text-xs">{{ formatText('attachments.selected', { count: selectedFiles.length }) }}: {{ selectedFiles.map(file => file.name).join(', ') }}</p>
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

    <TaskImagePreviewDialog
        :project-id="projectId" :task-id="taskId"
        :file="imagePreview" @close="imagePreview = null"
    />

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
