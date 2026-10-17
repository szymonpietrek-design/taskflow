<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Link, router } from '@inertiajs/vue3';
import {
    LoaderCircle,
    MessageSquare,
    Paperclip,
    Image as ImageIcon,
    Download,
    X,
    Pencil,
    Plus,
    Send,
    Trash2,
    Link2,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import TaskMarkdown from '@/components/tasks/TaskMarkdown.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { TaskAttachmentSummary, TaskMessageSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    taskId: number;
    messages: TaskMessageSummary[];
    availableAttachments: TaskAttachmentSummary[];
    canPost: boolean;
}>();

const editingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const messageBody = ref('');
const newMessageTextarea = ref<HTMLTextAreaElement | null>(null);
const editMessageTextarea = ref<HTMLTextAreaElement | null>(null);
const editingBody = ref('');
const composerTab = ref<'edit' | 'preview'>('edit');
const editTab = ref<'edit' | 'preview'>('edit');
const composerPreview = ref('');
const editPreview = ref('');
const composerPreviewLoading = ref(false);
const editPreviewLoading = ref(false);
const composerPreviewError = ref(false);
const editPreviewError = ref(false);
let composerRequestId = 0;
let editRequestId = 0;
const selectedFiles = ref<File[]>([]);
const selectedExistingIds = ref<number[]>([]);
const showLibrary = ref(false);
const filePicker = ref<HTMLInputElement | null>(null);
const posting = ref(false);
const postError = ref('');
const imagePreview = ref<TaskAttachmentSummary | null>(null);
const messagesUrl = computed(
    () => `/projects/${props.projectId}/tasks/${props.taskId}/messages`,
);
watch(
    () => props.taskId,
    () => {
        editingId.value = null;
        deletingId.value = null;
        resetComposer();
        imagePreview.value = null;
    },
);
const stayOnTask = { preserveScroll: true, preserveState: true };
const notifyError = () =>
    toast.error(t('fix.could_not_save_message'));

function formattedDate(value: string): string {
    return new Date(value).toLocaleString(locale.value === 'pl' ? 'pl-PL' : 'en-US');
}
const { t, locale } = useI18n();
const attachmentUrl = (file: TaskAttachmentSummary, action: 'preview' | 'download') =>
    `/projects/${props.projectId}/tasks/${props.taskId}/attachments/${file.id}/${action}`;

function resetComposer(): void {
    messageBody.value = '';
    selectedFiles.value = [];
    selectedExistingIds.value = [];
    showLibrary.value = false;
    postError.value = '';
    composerTab.value = 'edit';
    composerPreview.value = '';
    ++composerRequestId;
    if (filePicker.value) filePicker.value.value = '';
}
function selectFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const incoming = Array.from(input.files ?? []);
    postError.value = '';
    if (incoming.length > 5 || incoming.some(file => file.size > 20 * 1024 * 1024)) {
        postError.value = t('attachments.limits');
        selectedFiles.value = [];
        return;
    }
    selectedFiles.value = incoming;
}
function toggleExisting(id: number, checked: boolean): void {
    selectedExistingIds.value = checked
        ? [...selectedExistingIds.value, id]
        : selectedExistingIds.value.filter(existing => existing !== id);
}
function startEditing(message: TaskMessageSummary): void {
    editingId.value = message.id;
    editingBody.value = message.body;
    editTab.value = 'edit';
    editPreview.value = '';
    ++editRequestId;
}

function markdownReference(file: TaskAttachmentSummary, image: boolean): string {
    const label = file.name.replace(/[\r\n]+/g, ' ').replace(/[\\\[\]]/g, '\\$&');
    return `${image ? '!' : ''}[${label}](attachment:${file.id})`;
}

function insertAtCursor(value: string, field: HTMLTextAreaElement | null, mode: 'new' | 'edit'): void {
    const body = mode === 'new' ? messageBody : editingBody;
    const start = field?.selectionStart ?? body.value.length;
    const end = field?.selectionEnd ?? start;
    const before = body.value.slice(0, start);
    const after = body.value.slice(end);
    const prefix = before && !before.endsWith('\n') ? '\n' : '';
    const suffix = after && !after.startsWith('\n') ? '\n' : '';
    const inserted = `${prefix}${value}${suffix}`;
    if (body.value.length - (end - start) + inserted.length > 10000) {
        toast.error(t('messages.markdown.tooLong'));
        return;
    }
    body.value = before + inserted + after;
    if (mode === 'new') composerTab.value = 'edit';
    else editTab.value = 'edit';
    nextTick(() => {
        const textarea = mode === 'new' ? newMessageTextarea.value : editMessageTextarea.value;
        textarea?.focus();
        textarea?.setSelectionRange(start + inserted.length, start + inserted.length);
    });
}

function insertExisting(file: TaskAttachmentSummary, image: boolean, mode: 'new' | 'edit'): void {
    if (mode === 'new' && !selectedExistingIds.value.includes(file.id)) {
        if (selectedExistingIds.value.length >= 20) {
            toast.error(t('messages.markdown.maxFiles'));
            return;
        }
        selectedExistingIds.value = [...selectedExistingIds.value, file.id];
    }
    insertAtCursor(markdownReference(file, image), mode === 'new' ? newMessageTextarea.value : editMessageTextarea.value, mode);
}

async function requestPreview(url: string, body: string, ids?: number[]): Promise<string> {
    const xsrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
    const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length)) : null;
    const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!xsrfToken && !metaToken) throw new Error('Missing CSRF token');
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : { 'X-CSRF-TOKEN': metaToken! }),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ body, ...(ids ? { attachment_ids: ids } : {}) }),
    });
    if (!response.ok) throw new Error(`Markdown preview failed: ${response.status}`);
    const data: { html?: unknown } = await response.json();
    if (typeof data.html !== 'string') throw new Error('Invalid preview response');
    return data.html;
}

async function showComposerPreview(): Promise<void> {
    composerTab.value = 'preview';
    composerPreview.value = '';
    composerPreviewError.value = false;
    const current = ++composerRequestId;
    if (!messageBody.value.trim()) return;
    composerPreviewLoading.value = true;
    try {
        const html = await requestPreview(`${messagesUrl.value}/preview`, messageBody.value, selectedExistingIds.value);
        if (current === composerRequestId) composerPreview.value = html;
    } catch {
        if (current === composerRequestId) composerPreviewError.value = true;
    } finally {
        if (current === composerRequestId) composerPreviewLoading.value = false;
    }
}

async function showEditPreview(messageId: number): Promise<void> {
    editTab.value = 'preview';
    editPreview.value = '';
    editPreviewError.value = false;
    const current = ++editRequestId;
    if (!editingBody.value.trim()) return;
    editPreviewLoading.value = true;
    try {
        const html = await requestPreview(`${messagesUrl.value}/${messageId}/preview`, editingBody.value);
        if (current === editRequestId) editPreview.value = html;
    } catch {
        if (current === editRequestId) editPreviewError.value = true;
    } finally {
        if (current === editRequestId) editPreviewLoading.value = false;
    }
}

function showInlineImage(url: string): void {
    const match = url.match(/\/attachments\/(\d+)\/preview$/);
    if (!match) return;
    const file = props.availableAttachments.find(item => item.id === Number(match[1]));
    if (file?.is_image) imagePreview.value = file;
}

function submitMessage(): void {
    if (!props.canPost || posting.value) return;
    postError.value = '';
    router.post(messagesUrl.value, {
        body: messageBody.value,
        files: selectedFiles.value,
        attachment_ids: selectedExistingIds.value,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => { posting.value = true; },
        onFinish: () => { posting.value = false; },
        onSuccess: () => resetComposer(),
        onError: (errors) => {
            postError.value = String(
                errors.body || errors.files || errors.attachment_ids
                || Object.entries(errors).find(([key]) => key.startsWith('files.') || key.startsWith('attachment_ids.'))?.[1]
                || t('fix.could_not_save_message'),
            );
        },
    });
}
function unlink(messageId: number, attachmentId: number): void {
    router.delete(`${messagesUrl.value}/${messageId}/attachments/${attachmentId}`, {
        preserveScroll: true,
        onError: () => toast.error(t('messages.attachments.unlinkError')),
    });
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <MessageSquare class="size-4" /> {{ t('stage2.discussion') }}
                <span class="text-sm font-normal text-muted-foreground"
                    >({{ messages.length }})</span
                >
            </CardTitle>
            <CardDescription
                >{{ t('followup.discussion_help') }}</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-6">
            <div v-if="messages.length" class="space-y-5">
                <article
                    v-for="message in messages"
                    :id="`message-${message.id}`"
                    :key="message.id"
                    class="flex items-start gap-3"
                >
                    <UserAvatar
                        v-if="message.author"
                        :user-id="message.author.id"
                        :name="message.author.name"
                        compact
                    />
                    <div v-else class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground">?</div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"
                        >
                            <div
                                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                            >
                                <span class="font-medium">{{
                                    message.author?.name ?? t('fix.deleted_user')
                                }}</span>
                                <time
                                    :datetime="message.created_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formattedDate(message.created_at) }}
                                </time>
                                <span
                                    v-if="
                                        message.updated_at &&
                                        message.updated_at !==
                                            message.created_at
                                    "
                                    class="text-xs text-muted-foreground"
                                    >{{ t('stage2.edited') }}</span
                                >
                            </div>
                            <div
                                v-if="message.can_update || message.can_delete"
                                class="flex gap-1"
                            >
                                <Button
                                    v-if="message.can_update"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Edit message by ${message.author?.name ?? 'user'}`"
                                    @click="startEditing(message)"
                                >
                                    <Pencil class="size-3.5" /> {{ t('stage2.edit') }}
                                </Button>
                                <Dialog
                                    v-if="message.can_delete"
                                    :open="deletingId === message.id"
                                    @update:open="
                                        (value) =>
                                            (deletingId = value
                                                ? message.id
                                                : null)
                                    "
                                >
                                    <DialogTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            :aria-label="`Delete message by ${message.author?.name ?? 'user'}`"
                                        >
                                            <Trash2 class="size-3.5" /> {{ t('stage2.delete') }}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle
                                                >{{ t('stage2.delete_message') }}</DialogTitle
                                            >
                                            <DialogDescription
                                                >{{ t('audit.this_permanently_removes_your_message_from_the_discussion') }}</DialogDescription
                                            >
                                        </DialogHeader>
                                        <DialogFooter>
                                            <Form
                                                :action="`${messagesUrl}/${message.id}`"
                                                method="delete"
                                                :options="stayOnTask"
                                                @success="deletingId = null"
                                                @error="notifyError"
                                                v-slot="{ processing }"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    :disabled="processing"
                                                    ><LoaderCircle
                                                        v-if="processing"
                                                        class="size-4 animate-spin"
                                                    />{{
                                                        processing
                                                            ? t('fix.deleting')
                                                            : t('fix.delete_message')
                                                    }}</Button
                                                >
                                            </Form>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                        <Form
                            v-if="editingId === message.id"
                            :action="`${messagesUrl}/${message.id}`"
                            method="patch"
                            :options="stayOnTask"
                            @error="notifyError"
                            class="space-y-2"
                            @success="editingId = null"
                            v-slot="{ errors, processing }"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs text-muted-foreground">{{ t('messages.markdown.help') }}</span>
                                <div class="flex rounded-md border p-0.5">
                                    <Button type="button" size="sm" :variant="editTab === 'edit' ? 'secondary' : 'ghost'" :disabled="processing" @click="editTab = 'edit'">{{ t('taskDetails.editMarkdown') }}</Button>
                                    <Button type="button" size="sm" :variant="editTab === 'preview' ? 'secondary' : 'ghost'" :disabled="processing" @click="showEditPreview(message.id)">{{ t('taskDetails.previewMarkdown') }}</Button>
                                </div>
                            </div>
                            <textarea
                                v-show="editTab === 'edit'"
                                :id="`edit-message-${message.id}`"
                                ref="editMessageTextarea"
                                v-model="editingBody"
                                name="body"
                                :disabled="processing"
                                rows="4"
                                maxlength="10000"
                                required
                                :aria-label="t('stage2.edit_message')"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50"
                            />
                            <div v-if="editTab === 'preview'" class="min-h-20 rounded-md border p-3">
                                <p v-if="editPreviewLoading" class="text-sm text-muted-foreground">{{ t('taskDetails.loadingPreview') }}</p>
                                <p v-else-if="editPreviewError" role="alert" class="text-sm text-destructive">{{ t('taskDetails.previewError') }}</p>
                                <TaskMarkdown v-else-if="editPreview" :html="editPreview" zoom-images @image-click="showInlineImage" />
                                <p v-else class="text-sm text-muted-foreground">{{ t('taskDetails.emptyPreview') }}</p>
                            </div>
                            <div v-if="message.attachments.length" class="flex flex-wrap gap-1.5">
                                <template v-for="file in message.attachments" :key="file.id">
                                    <Button v-if="file.is_image" type="button" size="sm" variant="outline" :disabled="processing" :title="t('messages.markdown.insertImage')" @click="insertExisting(file, true, 'edit')"><ImageIcon class="size-3.5" />{{ file.name }}</Button>
                                    <Button type="button" size="sm" variant="outline" :disabled="processing" :title="t('messages.markdown.insertLink')" @click="insertExisting(file, false, 'edit')"><Link2 class="size-3.5" />{{ file.name }}</Button>
                                </template>
                            </div>
                            <InputError :message="errors.body" />
                            <div class="flex gap-2">
                                <Button size="sm" :disabled="processing"
                                    ><LoaderCircle
                                        v-if="processing"
                                        class="size-4 animate-spin"
                                    />{{
                                        processing ? t('audit.saving') : t('audit.save_changes')
                                    }}</Button
                                >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="processing"
                                    @click="editingId = null"
                                    >{{ t('stage2.cancel') }}</Button
                                >
                            </div>
                        </Form>
                        <TaskMarkdown v-else-if="message.body_html" :html="message.body_html" zoom-images @image-click="showInlineImage" />
                        <div v-if="message.attachments.length" class="flex flex-wrap items-center gap-2" :aria-label="t('messages.attachments.title')">
                            <div v-for="attachment in message.attachments" :key="attachment.id" class="inline-flex min-w-0 max-w-full items-center gap-1 rounded-md border bg-muted/30 px-2 py-1.5 text-xs">
                                <button v-if="attachment.is_image" type="button" class="inline-flex min-w-0 items-center gap-1.5 hover:underline" @click="imagePreview = attachment">
                                    <img :src="attachmentUrl(attachment, 'preview')" :alt="attachment.name" class="size-7 shrink-0 rounded object-cover" />
                                    <span class="max-w-44 truncate">{{ attachment.name }}</span>
                                </button>
                                <a v-else :href="attachmentUrl(attachment, 'download')" class="inline-flex min-w-0 items-center gap-1.5 hover:underline">
                                    <Paperclip class="size-3.5 shrink-0" /><span class="max-w-44 truncate">{{ attachment.name }}</span>
                                </a>
                                <a :href="attachmentUrl(attachment, 'download')" :aria-label="t('messages.attachments.download')" class="rounded p-1 hover:bg-muted"><Download class="size-3.5" /></a>
                                <button v-if="message.can_update" type="button" :aria-label="t('messages.attachments.unlink')" class="rounded p-1 hover:bg-muted" @click="unlink(message.id, attachment.id)"><X class="size-3.5" /></button>
                            </div>
                        </div>
                        <Button
                            v-if="
                                message.can_create_task &&
                                editingId !== message.id
                            "
                            as-child
                            size="sm"
                            variant="outline"
                        >
                            <Link
                                :href="`${messagesUrl}/${message.id}/create-task`"
                                ><Plus class="size-3.5" /> {{ t('stage2.create_task') }}</Link
                            >
                        </Button>
                    </div>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                {{ t('stage2.no_messages_yet_start_the_discussion_below') }}
            </p>

            <form v-if="canPost" class="space-y-3 border-t pt-5" @submit.prevent="submitMessage">
                <div class="flex items-center justify-between gap-2">
                    <label :for="`new-task-message-${taskId}`" class="text-sm font-medium">{{ t('stage2.write_a_message') }}</label>
                    <div class="flex rounded-md border p-0.5">
                        <Button type="button" size="sm" :variant="composerTab === 'edit' ? 'secondary' : 'ghost'" :disabled="posting" @click="composerTab = 'edit'">{{ t('taskDetails.editMarkdown') }}</Button>
                        <Button type="button" size="sm" :variant="composerTab === 'preview' ? 'secondary' : 'ghost'" :disabled="posting" @click="showComposerPreview">{{ t('taskDetails.previewMarkdown') }}</Button>
                    </div>
                </div>
                <textarea
                    v-show="composerTab === 'edit'"
                    :id="`new-task-message-${taskId}`"
                    ref="newMessageTextarea"
                    v-model="messageBody"
                    :disabled="posting"
                    rows="4"
                    maxlength="10000"
                    :placeholder="t('stage2.share_an_update_a_question_or_a_decision')"
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <div v-if="composerTab === 'preview'" class="min-h-24 rounded-md border p-3">
                    <p v-if="composerPreviewLoading" class="text-sm text-muted-foreground">{{ t('taskDetails.loadingPreview') }}</p>
                    <p v-else-if="composerPreviewError" role="alert" class="text-sm text-destructive">{{ t('taskDetails.previewError') }}</p>
                    <TaskMarkdown v-else-if="composerPreview" :html="composerPreview" zoom-images @image-click="showInlineImage" />
                    <p v-else class="text-sm text-muted-foreground">{{ t('taskDetails.emptyPreview') }}</p>
                </div>
                <p class="text-xs text-muted-foreground">{{ t('messages.markdown.help') }}</p>
                <input ref="filePicker" type="file" class="sr-only" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.json,.zip" :aria-label="t('messages.attachments.addFiles')" @change="selectFiles" />
                <div class="flex flex-wrap items-center gap-2">
                    <Button type="button" size="sm" variant="outline" :disabled="posting" @click="filePicker?.click()">
                        <Paperclip class="size-4" /> {{ t('messages.attachments.addFiles') }}
                    </Button>
                    <Button v-if="availableAttachments.length" type="button" size="sm" variant="outline" :disabled="posting" :aria-expanded="showLibrary" @click="showLibrary = !showLibrary">
                        <ImageIcon class="size-4" /> {{ t('messages.attachments.fromTask') }}
                    </Button>
                </div>
                <p v-if="selectedFiles.length" class="text-xs text-muted-foreground">{{ selectedFiles.map(file => file.name).join(', ') }}</p>
                <div v-if="showLibrary" class="max-h-48 space-y-1 overflow-y-auto rounded-md border p-2" :aria-label="t('messages.attachments.fromTask')">
                    <div v-for="attachment in availableAttachments" :key="attachment.id" class="flex items-center gap-2 rounded px-2 py-1.5 text-xs hover:bg-muted">
                        <input type="checkbox" :aria-label="attachment.name" :checked="selectedExistingIds.includes(attachment.id)" :disabled="posting || (selectedExistingIds.length >= 20 && !selectedExistingIds.includes(attachment.id))" @change="toggleExisting(attachment.id, ($event.target as HTMLInputElement).checked)" />
                        <ImageIcon v-if="attachment.is_image" class="size-3.5 shrink-0" /><Paperclip v-else class="size-3.5 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">{{ attachment.name }}</span>
                        <button v-if="attachment.is_image" type="button" class="shrink-0 rounded border px-2 py-1 hover:bg-background" :disabled="posting" :title="t('messages.markdown.insertImage')" @click.prevent="insertExisting(attachment, true, 'new')"><ImageIcon class="size-3.5" /></button>
                        <button type="button" class="shrink-0 rounded border px-2 py-1 hover:bg-background" :disabled="posting" :title="t('messages.markdown.insertLink')" @click.prevent="insertExisting(attachment, false, 'new')"><Link2 class="size-3.5" /></button>
                    </div>
                </div>
                <p v-if="selectedExistingIds.length" class="text-xs text-muted-foreground">{{ t('messages.attachments.selected') }}: {{ selectedExistingIds.length }}</p>
                <p v-if="postError" role="alert" class="text-sm text-destructive">{{ postError }}</p>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="posting || (!messageBody.trim() && !selectedFiles.length && !selectedExistingIds.length)">
                        <LoaderCircle v-if="posting" class="size-4 animate-spin" /><Send v-else class="size-4" />
                        {{ posting ? t('followup.posting') : t('followup.post') }}
                    </Button>
                </div>
            </form>
            <p v-else class="border-t pt-4 text-xs text-muted-foreground">
                {{ t('stage2.you_can_read_this_discussion_but_only_project_contributors_can_post_message') }}
            </p>
        </CardContent>
    </Card>

    <Dialog :open="imagePreview !== null" @update:open="(open) => { if (!open) imagePreview = null; }">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
            <DialogHeader><DialogTitle class="break-all">{{ imagePreview?.name }}</DialogTitle></DialogHeader>
            <img v-if="imagePreview" :src="attachmentUrl(imagePreview, 'preview')" :alt="imagePreview.name" class="mx-auto max-h-[70vh] max-w-full rounded-md object-contain" />
            <a v-if="imagePreview" :href="attachmentUrl(imagePreview, 'download')" class="inline-flex items-center gap-2 text-sm underline underline-offset-4"><Download class="size-4" />{{ t('messages.attachments.download') }}</a>
        </DialogContent>
    </Dialog>
</template>
