<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
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
const { t, formatText } = useI18n();
</script>

<template>
    <Head :title="t('stage2.create_task_from_message')" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Link
                :href="`${sourceUrl}#message-${message.id}`"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> {{ formatText('fix.back_to_reference', { reference: source_task.reference }) }}
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ t('stage2.create_task_from_message') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ formatText('fix.separate_task', { project: project.name }) }}
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base"
                    ><MessageSquare class="size-4" /> {{ t('stage2.source_message') }}</CardTitle
                >
                <CardDescription
                    >{{ formatText('fix.from_reference', { reference: source_task.reference }) }} ·
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
                    >{{ t('stage2.new_task') }}
                    <Badge variant="outline">{{ t('stage2.independent') }}</Badge></CardTitle
                >
                <CardDescription
                    >{{ t('audit.the_title_and_description_are_suggestions_edit_them_before_saving') }}</CardDescription
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
                        <Label for="message-task-title">{{ t('stage2.title') }}</Label>
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
                            >{{ t('stage2.description') }}
                            <span class="font-normal text-muted-foreground"
                                >{{ t('stage2.optional') }}</span
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
                            <Label for="message-task-type">{{ t('stage2.type') }}</Label>
                            <select
                                id="message-task-type"
                                name="type"
                                :class="selectClass"
                            >
                                <option value="1">{{ t('stage2.task') }}</option>
                                <option value="2">{{ t('stage2.feature') }}</option>
                                <option value="3">{{ t('stage2.improvement') }}</option>
                                <option value="4">{{ t('stage2.bug') }}</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="message-task-priority">{{ t('stage2.priority') }}</Label>
                            <select
                                id="message-task-priority"
                                name="priority"
                                :class="selectClass"
                            >
                                <option value="2">{{ t('stage2.normal') }}</option>
                                <option value="1">{{ t('stage2.low') }}</option>
                                <option value="3">{{ t('stage2.high') }}</option>
                                <option value="4">{{ t('stage2.urgent') }}</option>
                            </select>
                            <InputError :message="errors.priority" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="message-task-status"
                                >{{ t('stage2.status') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <select
                                id="message-task-status"
                                name="status_id"
                                :class="selectClass"
                            >
                                <option value="">{{ t('stage2.default_todo') }}</option>
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
                                >{{ t('stage2.assign_to') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <select
                                id="message-task-assignee"
                                name="assignee_id"
                                :class="selectClass"
                            >
                                <option value="">{{ t('stage2.unassigned') }}</option>
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
                                >{{ t('stage2.due_date') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
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
                                >{{ t('stage2.cancel') }}</Link
                            ></Button
                        >
                        <Button type="submit" :disabled="processing"
                            ><Plus class="size-4" /> {{ t('stage2.create_task') }}</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
