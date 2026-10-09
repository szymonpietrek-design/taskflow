<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeft, ArrowUp, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    { value: 1, label: 'Todo' },
    { value: 2, label: 'In progress' },
    { value: 3, label: 'Done' },
    { value: 4, label: 'Canceled' },
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
    router.patch(`${baseUrl}/${status.id}/move`, { direction }, {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
    });
}

function remove(status: Status): void {
    if (!window.confirm(`Delete status "${status.name}"? This cannot be undone.`)) return;
    router.delete(`${baseUrl}/${status.id}`, {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});
</script>

<template>
    <Head :title="`Statuses · ${project.name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link :href="`/projects/${project.id}`"><ArrowLeft class="size-4" /> Back to project</Link>
            </Button>
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{{ project.key }}</Badge>
                    <Badge variant="outline">Workflow</Badge>
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">Task statuses</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Configure the workflow for {{ project.name }}. Categories are fixed; status names and ordering are project-specific.
                </p>
            </div>
        </div>

        <div class="grid items-start gap-6" :class="can_manage ? 'lg:grid-cols-[minmax(0,1fr)_21rem]' : ''">
            <Card>
                <CardHeader>
                    <CardTitle>Workflow</CardTitle>
                    <CardDescription>Use the arrows to change the order of the statuses.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="!statuses.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No statuses configured for this project.
                    </p>
                    <div v-for="(status, index) in statuses" :key="status.id" class="rounded-lg border p-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-sm text-muted-foreground">
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ status.name }}</span>
                                    <Badge variant="secondary">{{ status.category_label }}</Badge>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ status.task_count }} {{ status.task_count === 1 ? 'task' : 'tasks' }}
                                </p>
                            </div>
                            <div v-if="can_manage" class="flex items-center gap-1">
                                <Button variant="ghost" size="icon-sm" :disabled="index === 0 || busy" aria-label="Move up" @click="move(status, 'up')">
                                    <ArrowUp class="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon-sm" :disabled="index === statuses.length - 1 || busy" aria-label="Move down" @click="move(status, 'down')">
                                    <ArrowDown class="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon-sm" :disabled="busy" aria-label="Edit status" @click="startEdit(status)">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon-sm" :disabled="busy || status.task_count > 0" aria-label="Delete status" @click="remove(status)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                        <Form v-if="can_manage && editingId === status.id"
                            :key="status.id"
                            :action="`${baseUrl}/${status.id}`" method="patch"
                            class="mt-4 grid gap-3 border-t pt-4"
                            v-slot="{ errors, processing }"
                            @success="editingId = null"
                        >
                            <div class="grid gap-1.5">
                                <Label :for="`status-name-${status.id}`">Status name</Label>
                                <Input :id="`status-name-${status.id}`" v-model="editingName" name="name" maxlength="80" required />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`status-category-${status.id}`">Category</Label>
                                <select :id="`status-category-${status.id}`" v-model.number="editingCategory" name="category"
                                    :disabled="status.task_count > 0"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs">
                                    <option v-for="category in categories" :key="category.value" :value="category.value">{{ category.label }}</option>
                                </select>
                                <input v-if="status.task_count > 0" type="hidden" name="category" :value="status.category" />
                                <p v-if="status.task_count > 0" class="text-xs text-muted-foreground">Category is locked while this status has tasks.</p>
                                <InputError :message="errors.category" />
                                <InputError :message="errors.status" />
                            </div>
                            <div class="flex items-center gap-2">
                                <Button size="sm" :disabled="processing">Save changes</Button>
                                <Button size="sm" variant="outline" type="button" @click="editingId = null">Cancel</Button>
                            </div>
                        </Form>
                    </div>
                    <p class="text-xs text-muted-foreground">Statuses containing tasks cannot be deleted or moved to another category. At least one Todo status must remain.</p>
                </CardContent>
            </Card>

            <Card v-if="can_manage">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><Plus class="size-4" /> New status</CardTitle>
                    <CardDescription>For example: Backlog, Development, Review, Testing.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Form :action="baseUrl" method="post" class="space-y-4" reset-on-success v-slot="{ errors, processing }">
                        <div class="grid gap-2">
                            <Label for="new-status-name">Name</Label>
                            <Input id="new-status-name" name="name" maxlength="80" required placeholder="Code Review" />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="new-status-category">Category</Label>
                            <select id="new-status-category" name="category" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs">
                                <option v-for="category in categories" :key="category.value" :value="category.value">{{ category.label }}</option>
                            </select>
                            <InputError :message="errors.category" />
                            <InputError :message="errors.status" />
                        </div>
                        <Button class="w-full" :disabled="processing"><Plus class="size-4" /> Add status</Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
