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
    ListPlus,
    Send,
    Trash2,
    Link2,
    ChevronDown,
    AlertTriangle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import TaskMarkdown from '@/components/tasks/TaskMarkdown.vue';
import TaskAttachmentLibraryPicker from '@/components/tasks/TaskAttachmentLibraryPicker.vue';
import TaskSelectedAttachments from '@/components/tasks/TaskSelectedAttachments.vue';
import TaskImagePreviewDialog from '@/components/tasks/TaskImagePreviewDialog.vue';
import { AsyncVisualMarkdownEditor } from '@/components/tasks/AsyncVisualMarkdownEditor';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
type AttachmentInsertRequest = { id: number; file: TaskAttachmentSummary; image: boolean };
let insertSequence = 0;
const newInsertRequests = ref<AttachmentInsertRequest[]>([]);
const editInsertRequests = ref<AttachmentInsertRequest[]>([]);
const composerKey = ref(0);
// Reading a task never downloads the rich editor; start it only on demand.
const composerActive = ref(false);
const composerUploading = ref(false);
const editingUploading = ref(false);
const recentUploads = ref<TaskAttachmentSummary[]>([]);
const library = computed(() => [
    ...props.availableAttachments,
    ...recentUploads.value.filter(a => !props.availableAttachments.some(b => b.id === a.id)),
]);
const editingBody = ref('');
// Edit all message attachments in one draft: unchecking takes effect only on Save.
const editSelectedIds = ref<number[]>([]);
const editLibraryOpen = ref(false);
const selectedFiles = ref<File[]>([]);
const selectedExistingIds = ref<number[]>([]);
const showLibrary = ref(false);
const editSelectedFiles = computed(() => editSelectedIds.value
    .map(id => library.value.find(file => file.id === id))
    .filter((file): file is TaskAttachmentSummary => file !== undefined));
const newSelectedFiles = computed(() => selectedExistingIds.value
    .map(id => library.value.find(file => file.id === id))
    .filter((file): file is TaskAttachmentSummary => file !== undefined));
// Attachment references in Markdown must always correspond to selected files.
// Otherwise the server deliberately renders "Attachment unavailable".
const unlinkedInlineIds = computed(() => {
    const ids = new Set<number>();
    const references = /(?:attachment:|\/attachments\/)(\d+)(?:\/(?:preview|download))?/gi;
    for (const match of editingBody.value.matchAll(references)) {
        const id = Number(match[1]);
        if (id > 0 && !editSelectedIds.value.includes(id)) ids.add(id);
    }
    return [...ids];
});
const unlinkedInlineNames = computed(() => unlinkedInlineIds.value.map(id =>
    library.value.find(file => file.id === id)?.name ?? `#${id}`,
));
const filePicker = ref<HTMLInputElement | null>(null);
const posting = ref(false);
const postError = ref('');
const imagePreview = ref<TaskAttachmentSummary | null>(null);
const messagesUrl = computed(
    () => `/projects/${props.projectId}/tasks/${props.taskId}/messages`,
);
// Old messages may contain attachment:ID links after a file was unlinked.
// Don't silently delete message content; offer its author a way to repair it.
function hasBrokenAttachmentReferences(message: TaskMessageSummary): boolean {
    const linked = new Set(message.attachments.map(file => file.id));
    for (const match of message.body.matchAll(/\battachment:([1-9][0-9]*)\b/gi)) {
        if (!linked.has(Number(match[1]))) return true;
    }
    return false;
}
watch(
    () => props.taskId,
    () => {
        editingId.value = null;
        deletingId.value = null;
        resetComposer();
        recentUploads.value = [];
        editSelectedIds.value = [];
        imagePreview.value = null;
    },
);
const stayOnTask = { preserveScroll: true, preserveState: true };
const notifyError = () =>
    toast.error(t('fix.could_not_save_message'));

function formattedDate(value: string): string {
    return new Date(value).toLocaleString(locale.value === 'pl' ? 'pl-PL' : 'en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
const { t, locale } = useI18n();
const attachmentUrl = (file: TaskAttachmentSummary, action: 'preview' | 'download') =>
    `/projects/${props.projectId}/tasks/${props.taskId}/attachments/${file.id}/${action}`;

function resetComposer(): void {
    messageBody.value = '';
    composerActive.value = false;
    composerUploading.value = false;
    selectedFiles.value = [];
    selectedExistingIds.value = [];
    showLibrary.value = false;
    postError.value = '';
    composerKey.value += 1;
    newInsertRequests.value = [];
    if (filePicker.value) filePicker.value.value = '';
}
function selectFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const incoming = Array.from(input.files ?? []);
    postError.value = '';
    if (incoming.length > 5 || incoming.some(file => file.size > 20 * 1024 * 1024)) {
        postError.value = t('attachments.limits');
        selectedFiles.value = [];
        input.value = '';
        return;
    }
    selectedFiles.value = incoming;
    // The form submits selectedFiles, not the native input value.
    // Clearing it lets users pick the same file again after removing it.
    input.value = '';
}
function removeSelectedFile(index: number): void {
    selectedFiles.value = selectedFiles.value.filter((_, position) => position !== index);
}
function toggleExisting(id: number, checked: boolean): void {
    selectedExistingIds.value = checked
        ? [...selectedExistingIds.value, id]
        : selectedExistingIds.value.filter(existing => existing !== id);
}
function toggleEditAttachment(id: number, checked: boolean): void {
    if (checked) {
        const file = library.value.find(attachment => attachment.id === id);
        if (file) addEditAttachment(file);
    } else {
        editSelectedIds.value = editSelectedIds.value.filter(existing => existing !== id);
    }
}
function startEditing(message: TaskMessageSummary): void {
    editingId.value = message.id;
    editingBody.value = message.body;
    editSelectedIds.value = message.attachments.map(file => file.id);
    editInsertRequests.value = [];
    editLibraryOpen.value = false;
    editingUploading.value = false;
}
function stopEditing(): void {
    editingId.value = null;
    editInsertRequests.value = [];
    editSelectedIds.value = [];
    editLibraryOpen.value = false;
}
function onEditUploaded(attachment: TaskAttachmentSummary): void {
    recentUploads.value.push(attachment);
    addEditAttachment(attachment);
    router.reload({ only: ['attachments'] });
}
function addEditAttachment(file: TaskAttachmentSummary): boolean {
    if (editSelectedIds.value.includes(file.id)) return true;
    if (editSelectedIds.value.length >= 20) {
        toast.error(t('messages.markdown.maxFiles'));
        return false;
    }
    editSelectedIds.value.push(file.id);
    return true;
}

function onUploaded(attachment: TaskAttachmentSummary): void {
    recentUploads.value.push(attachment);
    if (!selectedExistingIds.value.includes(attachment.id)) {
        selectedExistingIds.value.push(attachment.id);
    }
    // The file is in the task library immediately, even if the draft is discarded.
    router.reload({ only: ['attachments'] });
}
// A reactive insertion request reaches the editor even while its async component
// is still loading. Calling a method through a ref to the async Vue wrapper was
// unreliable and could leave both attachment buttons seemingly inactive.
function insertExisting(file: TaskAttachmentSummary, image: boolean, mode: 'new' | 'edit'): void {
    if (mode === 'new') {
        if (!selectedExistingIds.value.includes(file.id)) {
            if (selectedExistingIds.value.length >= 20) {
                toast.error(t('messages.markdown.maxFiles'));
                return;
            }
            selectedExistingIds.value = [...selectedExistingIds.value, file.id];
        }
        composerActive.value = true;
        newInsertRequests.value = [...newInsertRequests.value, { id: ++insertSequence, file, image }];
    } else {
        if (!addEditAttachment(file)) return;
        editInsertRequests.value = [...editInsertRequests.value, { id: ++insertSequence, file, image }];
    }
}

function showInlineImage(url: string): void {
    const match = url.match(/\/attachments\/(\d+)\/preview$/);
    if (!match) return;
    const file = library.value.find(item => item.id === Number(match[1]));
    if (file?.is_image) imagePreview.value = file;
}

function submitMessage(): void {
    if (!props.canPost || posting.value || composerUploading.value) return;
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
                    class="flex scroll-mt-6 items-start gap-3 border-b pb-5 last:border-b-0 last:pb-0 target:rounded-md target:bg-muted/30"
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
                                v-if="((message.can_create_task || message.can_update) && editingId !== message.id) || message.can_delete"
                                class="flex shrink-0 items-center gap-0.5 rounded-md p-0.5"
                                role="group"
                                :aria-label="t('messages.actions')"
                            >
                                <TooltipProvider :delay-duration="150">
                                    <Tooltip v-if="message.can_create_task && editingId !== message.id">
                                        <TooltipTrigger as-child>
                                            <Button
                                                as-child
                                                variant="ghost"
                                                size="icon-lg"
                                                class="text-muted-foreground hover:bg-accent hover:text-foreground"
                                            >
                                                <Link
                                                    :href="`${messagesUrl}/${message.id}/create-task`"
                                                    :aria-label="t('stage2.create_task_from_message')"
                                                >
                                                    <ListPlus class="size-4" aria-hidden="true" />
                                                </Link>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent side="top">{{ t('stage2.create_task') }}</TooltipContent>
                                    </Tooltip>
                                    <Tooltip v-if="message.can_update && editingId !== message.id">
                                        <TooltipTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-lg"
                                                class="text-muted-foreground hover:bg-accent hover:text-foreground"
                                                :aria-label="`${t('stage2.edit')} — ${message.author?.name ?? t('fix.deleted_user')}`"
                                                @click="startEditing(message)"
                                            >
                                                <Pencil class="size-4" aria-hidden="true" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent side="top">{{ t('stage2.edit') }}</TooltipContent>
                                    </Tooltip>
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
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <DialogTrigger as-child>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-lg"
                                                        class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                        :aria-label="`${t('stage2.delete')} — ${message.author?.name ?? t('fix.deleted_user')}`"
                                                    >
                                                        <Trash2 class="size-4" aria-hidden="true" />
                                                    </Button>
                                                </DialogTrigger>
                                            </TooltipTrigger>
                                            <TooltipContent side="top">{{ t('stage2.delete') }}</TooltipContent>
                                        </Tooltip>
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
                                </TooltipProvider>
                            </div>
                        </div>
                        <Form
                            v-if="editingId === message.id"
                            :action="`${messagesUrl}/${message.id}`"
                            method="patch"
                            :options="stayOnTask"
                            @error="notifyError"
                            class="space-y-2"
                            @success="stopEditing"
                            v-slot="{ errors, processing }"
                        >
                            <input type="hidden" name="body" :value="editingBody" />
                            <input type="hidden" name="sync_attachments" value="1" />
                            <input v-for="id in editSelectedIds" :key="id" type="hidden" name="attachment_ids[]" :value="id" />
                            <AsyncVisualMarkdownEditor
                                :insert-requests="editInsertRequests"
                                v-model="editingBody"
                                :project-id="projectId"
                                :task-id="taskId"
                                :attachments="library"
                                :disabled="processing"
                                :placeholder="t('stage2.edit_message')"
                                compact
                                :can-upload="editSelectedIds.length < 20"
                                @uploaded="onEditUploaded"
                                @uploading="editingUploading = $event"
                            />
                            <div class="flex flex-wrap items-center gap-2">
                                <Button type="button" size="sm" variant="outline" :disabled="processing || editingUploading" :aria-expanded="editLibraryOpen" @click="editLibraryOpen = !editLibraryOpen">
                                    <Paperclip class="size-3.5" /> {{ t('messages.attachments.manage') }}
                                    <span v-if="editSelectedIds.length" class="rounded bg-muted px-1.5 text-xs tabular-nums">{{ editSelectedIds.length }}</span>
                                    <ChevronDown class="size-3.5 text-muted-foreground transition-transform" :class="editLibraryOpen ? 'rotate-180' : ''" aria-hidden="true" />
                                </Button>
                            </div>
                            <TaskSelectedAttachments
                                v-if="!editLibraryOpen"
                                :files="editSelectedFiles"
                                :disabled="processing || editingUploading"
                                @remove="toggleEditAttachment($event, false)"
                            />
                            <TaskAttachmentLibraryPicker
                                :project-id="projectId"
                                :task-id="taskId"
                                v-if="editLibraryOpen"
                                scope="edit"
                                editing
                                :attachments="library"
                                :selected-ids="editSelectedIds"
                                :disabled="processing || editingUploading"
                                @toggle="toggleEditAttachment"
                                @insert="(file, image) => insertExisting(file, image, 'edit')"
                            />
                            <div v-if="unlinkedInlineNames.length" role="alert" class="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/5 p-3 text-sm">
                                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                                <div class="min-w-0 space-y-1">
                                    <p class="font-medium">{{ t('messages.attachments.inlineWarningTitle') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('messages.attachments.inlineWarningHelp') }}</p>
                                    <p class="break-words text-xs text-muted-foreground">{{ unlinkedInlineNames.join(', ') }}</p>
                                </div>
                            </div>
                            <InputError :message="errors.body" />
                            <InputError :message="errors.attachment_ids ?? errors['attachment_ids.0']" />
                            <div class="flex gap-2">
                                <Button size="sm" :disabled="processing || editingUploading || unlinkedInlineNames.length > 0"
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
                                    :disabled="processing || editingUploading"
                                    @click="stopEditing"
                                    >{{ t('stage2.cancel') }}</Button
                                >
                            </div>
                        </Form>
                        <TaskMarkdown v-else-if="message.body_html" :html="message.body_html" zoom-images @image-click="showInlineImage" />
                        <div
                            v-if="editingId !== message.id && hasBrokenAttachmentReferences(message)"
                            class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md border border-amber-500/25 bg-amber-500/5 px-3 py-2 text-xs"
                            role="status"
                        >
                            <AlertTriangle class="size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                            <span class="min-w-0 flex-1 text-muted-foreground">{{ t('messages.attachments.repairHint') }}</span>
                            <Button
                                v-if="message.can_update"
                                type="button"
                                size="sm"
                                variant="outline"
                                class="shrink-0"
                                @click="startEditing(message)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" /> {{ t('messages.attachments.repairAction') }}
                            </Button>
                        </div>
                        <div v-if="editingId !== message.id && message.attachments.length" class="flex flex-wrap items-center gap-2" :aria-label="t('messages.attachments.title')">
                            <div v-for="attachment in message.attachments" :key="attachment.id" class="inline-flex min-w-0 max-w-full items-center gap-1 rounded-md border bg-muted/30 px-2 py-1.5 text-xs">
                                <button v-if="attachment.is_image" type="button" class="inline-flex min-w-0 items-center gap-1.5 hover:underline" @click="imagePreview = attachment">
                                    <img :src="attachmentUrl(attachment, 'preview')" :alt="attachment.name" class="size-7 shrink-0 rounded object-cover" />
                                    <span class="max-w-44 truncate">{{ attachment.name }}</span>
                                </button>
                                <a v-else :href="attachmentUrl(attachment, 'download')" class="inline-flex min-w-0 items-center gap-1.5 hover:underline">
                                    <Paperclip class="size-3.5 shrink-0" /><span class="max-w-44 truncate">{{ attachment.name }}</span>
                                </a>
                                <a
                                    :href="attachmentUrl(attachment, 'download')"
                                    :aria-label="`${t('messages.attachments.download')} — ${attachment.name}`"
                                    :title="t('messages.attachments.download')"
                                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                ><Download class="size-4" aria-hidden="true" /></a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                {{ t('stage2.no_messages_yet_start_the_discussion_below') }}
            </p>

            <form v-if="canPost" class="space-y-3 border-t pt-5" @submit.prevent="submitMessage">
                <label class="text-sm font-medium">{{ t('stage2.write_a_message') }}</label>
                <button
                    v-if="!composerActive"
                    type="button"
                    class="flex min-h-24 w-full items-start rounded-md border border-input bg-background px-3 py-3 text-left text-sm text-muted-foreground transition-colors hover:border-ring hover:bg-muted/30 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :aria-label="t('stage2.write_a_message')"
                    @click="composerActive = true"
                >
                    {{ t('stage2.share_an_update_a_question_or_a_decision') }}
                </button>
                <AsyncVisualMarkdownEditor
                    v-else
                    :key="composerKey"
                    :insert-requests="newInsertRequests"
                    v-model="messageBody"
                    :project-id="projectId"
                    :task-id="taskId"
                    :attachments="library"
                    :disabled="posting"
                    :placeholder="t('stage2.share_an_update_a_question_or_a_decision')"
                    compact
                    :can-upload="selectedExistingIds.length < 20"
                    @uploaded="onUploaded"
                    @uploading="composerUploading = $event"
                />
                <p class="text-xs text-muted-foreground">{{ t('messages.markdown.help') }}</p>
                <input ref="filePicker" type="file" class="sr-only" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.json,.zip" :aria-label="t('messages.attachments.addFiles')" @change="selectFiles" />
                <div class="flex flex-wrap items-center gap-2">
                    <Button type="button" size="sm" variant="outline" :disabled="posting" @click="filePicker?.click()">
                        <Paperclip class="size-4" /> {{ t('messages.attachments.addFiles') }}
                    </Button>
                    <Button v-if="library.length" type="button" size="sm" variant="outline" :disabled="posting" :aria-expanded="showLibrary" @click="showLibrary = !showLibrary">
                        <Paperclip class="size-4" /> {{ t('messages.attachments.manage') }}
                        <span v-if="selectedExistingIds.length" class="rounded bg-muted px-1.5 text-xs tabular-nums">{{ selectedExistingIds.length }}</span>
                        <ChevronDown class="size-3.5 text-muted-foreground transition-transform" :class="showLibrary ? 'rotate-180' : ''" aria-hidden="true" />
                    </Button>
                </div>
                <div v-if="selectedFiles.length" class="flex flex-wrap gap-2" :aria-label="t('messages.attachments.pendingFiles')">
                    <div v-for="(file, index) in selectedFiles" :key="`${file.name}-${index}`" class="inline-flex max-w-full items-center gap-1.5 rounded-md border bg-muted/30 py-1 pl-2.5 pr-1 text-xs">
                        <Paperclip class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <span class="max-w-64 truncate" :title="file.name">{{ file.name }}</span>
                        <Button type="button" variant="ghost" size="icon-sm" class="size-6 shrink-0" :disabled="posting" :aria-label="`${t('messages.attachments.removePendingFile')} — ${file.name}`" @click="removeSelectedFile(index)">
                            <X class="size-3.5" aria-hidden="true" />
                        </Button>
                    </div>
                </div>
                <TaskSelectedAttachments
                    v-if="!showLibrary"
                    :files="newSelectedFiles"
                    :disabled="posting || composerUploading"
                    @remove="toggleExisting($event, false)"
                />
                <TaskAttachmentLibraryPicker
                    :project-id="projectId"
                    :task-id="taskId"
                    v-if="showLibrary"
                    scope="new"
                    :attachments="library"
                    :selected-ids="selectedExistingIds"
                    :disabled="posting || composerUploading"
                    @toggle="toggleExisting"
                    @insert="(file, image) => insertExisting(file, image, 'new')"
                />
                <p v-if="postError" role="alert" class="text-sm text-destructive">{{ postError }}</p>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="posting || composerUploading || (!messageBody.trim() && !selectedFiles.length && !selectedExistingIds.length)">
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

    <TaskImagePreviewDialog
        :project-id="projectId" :task-id="taskId"
        :file="imagePreview" @close="imagePreview = null"
    />
</template>
