<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
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

type Status = {
    id: number;
    name: string;
    category: number;
    category_label: string;
    task_count: number;
};

const props = defineProps<{
    project: { id: number; name: string; key: string };
    can_manage: boolean;
    statuses: Status[];
}>();

const categories = [
    { value: 1, label: 'fix.category_todo' },
    { value: 2, label: 'fix.category_progress' },
    { value: 3, label: 'fix.category_done' },
    { value: 4, label: 'fix.category_canceled' },
];

const editingId = ref<number | null>(null);
const editingName = ref('');
const editingCategory = ref(1);
const busy = ref(false);

const baseUrl = `/projects/${props.project.id}/statuses`;

function startEdit(status: Status): void {
    editingId.value = status.id;
    editingName.value = status.name;
    editingCategory.value = status.category;
}

function move(status: Status, direction: 'up' | 'down'): void {
    router.patch(
        `${baseUrl}/${status.id}/move`,
        { direction },
        {
            preserveScroll: true,
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function remove(status: Status): void {
    if (
        !window.confirm(
            formatText('fix.delete_status', { status: status.name }),
        )
    )
        return;
    router.delete(`${baseUrl}/${status.id}`, {
        preserveScroll: true,
        onStart: () => {
            busy.value = true;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});
const { t, formatText, countLabel, displayLabel } = useI18n();
</script>

<template>
    <Head :title="`${t('fix.statuses')} · ${project.name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link :href="`/projects/${project.id}`"
                    ><ArrowLeft class="size-4" /> {{ t('stage2.back_to_project') }}</Link
                >
            </Button>
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{{ project.key }}</Badge>
                    <Badge variant="outline">{{ t('stage2.workflow') }}</Badge>
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ t('stage2.task_statuses') }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ formatText('fix.statuses_help', { project: project.name }) }}
                </p>
            </div>
        </div>

        <div
            class="grid items-start gap-6"
            :class="can_manage ? 'lg:grid-cols-[minmax(0,1fr)_21rem]' : ''"
        >
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('stage2.workflow') }}</CardTitle>
                    <CardDescription
                        >{{ t('audit.use_the_arrows_to_change_the_order_of_the_statuses') }}</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="!statuses.length"
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        {{ t('stage2.no_statuses_configured_for_this_project') }}
                    </p>
                    <div
                        v-for="(status, index) in statuses"
                        :key="status.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-sm text-muted-foreground"
                            >
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{
                                        status.name
                                    }}</span>
                                    <Badge variant="secondary">{{
                                        displayLabel(status.category_label)
                                    }}</Badge>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ countLabel('task', status.task_count) }}
                                </p>
                            </div>
                            <div
                                v-if="can_manage"
                                class="flex items-center gap-1"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="index === 0 || busy"
                                    :aria-label="t('stage2.move_up')"
                                    @click="move(status, 'up')"
                                >
                                    <ArrowUp class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="
                                        index === statuses.length - 1 || busy
                                    "
                                    :aria-label="t('stage2.move_down')"
                                    @click="move(status, 'down')"
                                >
                                    <ArrowDown class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="busy"
                                    :aria-label="t('stage2.edit_status')"
                                    @click="startEdit(status)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="busy || status.task_count > 0"
                                    :aria-label="t('stage2.delete_status')"
                                    @click="remove(status)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                        <Form
                            v-if="can_manage && editingId === status.id"
                            :key="status.id"
                            :action="`${baseUrl}/${status.id}`"
                            method="patch"
                            class="mt-4 grid gap-3 border-t pt-4"
                            v-slot="{ errors, processing }"
                            @success="editingId = null"
                        >
                            <div class="grid gap-1.5">
                                <Label :for="`status-name-${status.id}`"
                                    >{{ t('stage2.status_name') }}</Label
                                >
                                <Input
                                    :id="`status-name-${status.id}`"
                                    v-model="editingName"
                                    name="name"
                                    maxlength="80"
                                    required
                                />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`status-category-${status.id}`"
                                    >{{ t('stage2.category') }}</Label
                                >
                                <select
                                    :id="`status-category-${status.id}`"
                                    v-model.number="editingCategory"
                                    name="category"
                                    :disabled="status.task_count > 0"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                                >
                                    <option
                                        v-for="category in categories"
                                        :key="category.value"
                                        :value="category.value"
                                    >
                                        {{ t(category.label) }}
                                    </option>
                                </select>
                                <input
                                    v-if="status.task_count > 0"
                                    type="hidden"
                                    name="category"
                                    :value="status.category"
                                />
                                <p
                                    v-if="status.task_count > 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ t('stage2.category_is_locked_while_this_status_has_tasks') }}
                                </p>
                                <InputError :message="errors.category" />
                                <InputError :message="errors.status" />
                            </div>
                            <div class="flex items-center gap-2">
                                <Button size="sm" :disabled="processing"
                                    >{{ t('stage2.save_changes') }}</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    type="button"
                                    @click="editingId = null"
                                    >{{ t('stage2.cancel') }}</Button
                                >
                            </div>
                        </Form>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('stage2.statuses_containing_tasks_cannot_be_deleted_or_moved_to_another_category_at') }}
                    </p>
                </CardContent>
            </Card>

            <Card v-if="can_manage">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Plus class="size-4" /> {{ t('stage2.new_status') }}</CardTitle
                    >
                    <CardDescription
                        >{{ t('audit.for_example_backlog_development_review_testing') }}</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Form
                        :action="baseUrl"
                        method="post"
                        class="space-y-4"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="new-status-name">{{ t('stage2.name') }}</Label>
                            <Input
                                id="new-status-name"
                                name="name"
                                maxlength="80"
                                required
                                :placeholder="t('audit.code_review')"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="new-status-category">{{ t('stage2.category') }}</Label>
                            <select
                                id="new-status-category"
                                name="category"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                            >
                                <option
                                    v-for="category in categories"
                                    :key="category.value"
                                    :value="category.value"
                                >
                                    {{ t(category.label) }}
                                </option>
                            </select>
                            <InputError :message="errors.category" />
                            <InputError :message="errors.status" />
                        </div>
                        <Button class="w-full" :disabled="processing"
                            ><Plus class="size-4" /> {{ t('stage2.add_status') }}</Button
                        >
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
