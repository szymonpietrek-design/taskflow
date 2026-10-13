<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import {
    LoaderCircle,
    MessageSquare,
    Pencil,
    Plus,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import type { TaskMessageSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    taskId: number;
    messages: TaskMessageSummary[];
    canPost: boolean;
}>();

const editingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const messagesUrl = computed(
    () => `/projects/${props.projectId}/tasks/${props.taskId}/messages`,
);
watch(
    () => props.taskId,
    () => {
        editingId.value = null;
        deletingId.value = null;
    },
);
const stayOnTask = { preserveScroll: true, preserveState: true };
const notifyError = () =>
    toast.error('Could not save the message. Check the form and try again.');

function initials(name: string): string {
    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase() || '?'
    );
}

function formattedDate(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <MessageSquare class="size-4" /> Discussion
                <span class="text-sm font-normal text-muted-foreground"
                    >({{ messages.length }})</span
                >
            </CardTitle>
            <CardDescription
                >Messages and decisions about this task. System activity is
                recorded separately.</CardDescription
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
                    <Avatar class="size-9 shrink-0">
                        <AvatarFallback>{{
                            initials(message.author?.name ?? '?')
                        }}</AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"
                        >
                            <div
                                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                            >
                                <span class="font-medium">{{
                                    message.author?.name ?? 'Deleted user'
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
                                    >(edited)</span
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
                                    @click="editingId = message.id"
                                >
                                    <Pencil class="size-3.5" /> Edit
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
                                            <Trash2 class="size-3.5" /> Delete
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle
                                                >Delete message?</DialogTitle
                                            >
                                            <DialogDescription
                                                >This permanently removes your
                                                message from the
                                                discussion.</DialogDescription
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
                                                            ? 'Deleting…'
                                                            : 'Delete message'
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
                            <textarea
                                :id="`edit-message-${message.id}`"
                                name="body"
                                :value="message.body"
                                :disabled="processing"
                                rows="3"
                                maxlength="10000"
                                required
                                aria-label="Edit message"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError :message="errors.body" />
                            <div class="flex gap-2">
                                <Button size="sm" :disabled="processing"
                                    ><LoaderCircle
                                        v-if="processing"
                                        class="size-4 animate-spin"
                                    />{{
                                        processing ? 'Saving…' : 'Save changes'
                                    }}</Button
                                >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="processing"
                                    @click="editingId = null"
                                    >Cancel</Button
                                >
                            </div>
                        </Form>
                        <p
                            v-else
                            class="text-sm leading-relaxed break-words whitespace-pre-wrap"
                        >
                            {{ message.body }}
                        </p>
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
                                ><Plus class="size-3.5" /> Create task</Link
                            >
                        </Button>
                    </div>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No messages yet. Start the discussion below.
            </p>

            <Form
                v-if="canPost"
                :action="messagesUrl"
                method="post"
                :options="stayOnTask"
                @error="notifyError"
                class="space-y-3 border-t pt-5"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <label
                    :for="`new-task-message-${taskId}`"
                    class="text-sm font-medium"
                    >Write a message</label
                >
                <textarea
                    :id="`new-task-message-${taskId}`"
                    name="body"
                    :disabled="processing"
                    rows="4"
                    maxlength="10000"
                    required
                    placeholder="Share an update, a question or a decision..."
                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="errors.body" />
                <div class="flex justify-end">
                    <Button :disabled="processing"
                        ><LoaderCircle
                            v-if="processing"
                            class="size-4 animate-spin"
                        /><Send v-else class="size-4" />{{
                            processing ? 'Posting…' : 'Post message'
                        }}</Button
                    >
                </div>
            </Form>
            <p v-else class="border-t pt-4 text-xs text-muted-foreground">
                You can read this discussion, but only project contributors can
                post messages.
            </p>
        </CardContent>
    </Card>
</template>
