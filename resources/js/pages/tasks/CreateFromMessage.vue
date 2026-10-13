<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageSquare, Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    TaskParentSummary,
    TaskProjectSummary,
    TaskStatusSummary,
    TaskUserSummary,
} from '@/types/task';

const props = defineProps<{
    project: TaskProjectSummary;
    source_task: TaskParentSummary;
    message: { id: number; body: string; author: string };
    suggested_title: string;
    statuses: TaskStatusSummary[];
    assignees: TaskUserSummary[];
}>();

const sourceUrl = `/projects/${props.project.id}/tasks/${props.source_task.id}`;
const submitUrl = `${sourceUrl}/messages/${props.message.id}/create-task`;
const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] },
});
</script>

<template>
    <Head title="Create task from message" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Link
                :href="`${sourceUrl}#message-${message.id}`"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Back to {{ source_task.reference }}
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">
                Create task from message
            </h1>
            <p class="text-sm text-muted-foreground">
                Creates a separate task in {{ project.name }}, not a subtask.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base"
                    ><MessageSquare class="size-4" /> Source message</CardTitle
                >
                <CardDescription
                    >From {{ source_task.reference }} ·
                    {{ message.author }}</CardDescription
                >
            </CardHeader>
            <CardContent>
                <p class="text-sm break-words whitespace-pre-wrap">
                    {{ message.body }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle
                    >New task
                    <Badge variant="outline">Independent</Badge></CardTitle
                >
                <CardDescription
                    >The title and description are suggestions. Edit them before
                    saving.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <Form
                    :action="submitUrl"
                    method="post"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="space-y-1.5">
                        <Label for="message-task-title">Title</Label>
                        <Input
                            id="message-task-title"
                            name="title"
                            maxlength="200"
                            required
                            :default-value="suggested_title"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="message-task-description"
                            >Description
                            <span class="font-normal text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <textarea
                            id="message-task-description"
                            name="description"
                            :value="message.body"
                            rows="6"
                            maxlength="10000"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="message-task-type">Type</Label>
                            <select
                                id="message-task-type"
                                name="type"
                                :class="selectClass"
                            >
                                <option value="1">Task</option>
                                <option value="2">Feature</option>
                                <option value="3">Improvement</option>
                                <option value="4">Bug</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="message-task-priority">Priority</Label>
                            <select
                                id="message-task-priority"
                                name="priority"
                                :class="selectClass"
                            >
                                <option value="2">Normal</option>
                                <option value="1">Low</option>
                                <option value="3">High</option>
                                <option value="4">Urgent</option>
                            </select>
                            <InputError :message="errors.priority" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="message-task-status"
                                >Status
                                <span class="font-normal text-muted-foreground"
                                    >(optional)</span
                                ></Label
                            >
                            <select
                                id="message-task-status"
                                name="status_id"
                                :class="selectClass"
                            >
                                <option value="">Default (Todo)</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status.id"
                                    :value="status.id"
                                >
                                    {{ status.name }}
                                </option>
                            </select>
                            <InputError :message="errors.status_id" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="message-task-assignee"
                                >Assign to
                                <span class="font-normal text-muted-foreground"
                                    >(optional)</span
                                ></Label
                            >
                            <select
                                id="message-task-assignee"
                                name="assignee_id"
                                :class="selectClass"
                            >
                                <option value="">Unassigned</option>
                                <option
                                    v-for="user in assignees"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }}
                                </option>
                            </select>
                            <InputError :message="errors.assignee_id" />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="message-task-due"
                                >Due date
                                <span class="font-normal text-muted-foreground"
                                    >(optional)</span
                                ></Label
                            >
                            <Input
                                id="message-task-due"
                                name="due_date"
                                type="date"
                            />
                            <InputError :message="errors.due_date" />
                        </div>
                    </div>
                    <InputError :message="errors.parent_id" />
                    <div class="flex justify-end gap-2">
                        <Button as-child type="button" variant="outline"
                            ><Link :href="`${sourceUrl}#message-${message.id}`"
                                >Cancel</Link
                            ></Button
                        >
                        <Button type="submit" :disabled="processing"
                            ><Plus class="size-4" /> Create task</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
