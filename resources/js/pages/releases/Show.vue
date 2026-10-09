<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Clock3, Package, Plus, X } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';

type Release = {
    id: number;
    version: string;
    name: string | null;
    description: string | null;
    status: string;
    status_label: string;
    planned_for: string | null;
    released_at: string | null;
    created_at: string | null;
};

type ReleaseTask = { id: number; reference: string; title: string; status: string; completed: boolean };

const props = defineProps<{
    project: { id: number; name: string; key: string };
    release: Release;
    can_manage_tasks: boolean;
    tasks: {
        data: ReleaseTask[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    task_count: number;
    available_tasks: ReleaseTask[];
    available_count: number;
    task_search: string;
}>();

const releaseUrl = `/projects/${props.project.id}/releases/${props.release.id}`;

defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`v${release.version} · ${project.name}`" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Button as-child size="sm" variant="ghost" class="-ml-3 w-fit">
            <Link :href="`/projects/${project.id}/releases`"><ArrowLeft class="size-4" /> All releases</Link>
        </Button>
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge :variant="release.status === 'released' ? 'default' : 'outline'">{{ release.status_label }}</Badge>
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">v{{ release.version }}</h1>
                <p v-if="release.name" class="mt-1 text-base text-muted-foreground">{{ release.name }}</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <Card>
                <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><CalendarDays class="size-4" /> Planned date</CardTitle></CardHeader>
                <CardContent class="font-medium">{{ release.planned_for ?? 'Not scheduled' }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><Clock3 class="size-4" /> Released at</CardTitle></CardHeader>
                <CardContent class="font-medium">{{ release.released_at ? new Date(release.released_at).toLocaleString() : 'Not released' }}</CardContent>
            </Card>
        </div>
        <Card>
            <CardHeader><CardTitle class="flex items-center gap-2"><Package class="size-4" /> Release notes</CardTitle></CardHeader>
            <CardContent>
                <p v-if="release.description" class="whitespace-pre-wrap text-sm leading-relaxed">{{ release.description }}</p>
                <p v-else class="text-sm text-muted-foreground">No description yet. Tasks and changelog generation will be added in a later stage.</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>Included tasks ({{ task_count }})</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="tasks.data.length === 0" class="text-sm text-muted-foreground">No tasks assigned to this release yet.</p>
                <div v-for="task in tasks.data" :key="task.id" class="flex items-center justify-between gap-3 border-b pb-3 last:border-0">
                    <div class="min-w-0 space-y-1">
                        <Link :href="`/projects/${project.id}/tasks/${task.id}`" class="text-sm font-medium hover:underline">{{ task.reference }} · {{ task.title }}</Link>
                        <p class="text-xs text-muted-foreground">{{ task.status }} <span v-if="task.completed">· Completed</span></p>
                    </div>
                    <Form v-if="can_manage_tasks" :action="`${releaseUrl}/tasks/${task.id}`" method="delete" v-slot="{ processing }">
                        <Button type="submit" size="sm" variant="outline" :disabled="processing"><X class="size-4" /> Remove</Button>
                    </Form>
                </div>
                <div v-if="tasks.last_page > 1" class="flex items-center justify-between gap-2">
                    <Button v-if="tasks.prev_page_url" as-child size="sm" variant="outline"><Link :href="tasks.prev_page_url">Previous</Link></Button>
                    <span v-else></span>
                    <span class="text-xs text-muted-foreground">Page {{ tasks.current_page }} / {{ tasks.last_page }}</span>
                    <Button v-if="tasks.next_page_url" as-child size="sm" variant="outline"><Link :href="tasks.next_page_url">Next</Link></Button>
                </div>
            </CardContent>
        </Card>
        <Card v-if="can_manage_tasks">
            <CardHeader><CardTitle>Add existing task</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <Form :action="releaseUrl" method="get" class="flex flex-wrap gap-2">
                    <Input name="q" type="search" :default-value="task_search" placeholder="Search title or APP-12" class="min-w-48 flex-1" />
                    <Button type="submit" variant="outline">Search</Button>
                </Form>
                <p class="text-xs text-muted-foreground">{{ available_count }} available tasks<span v-if="available_count > available_tasks.length"> · showing latest {{ available_tasks.length }} — narrow your search</span></p>
                <Form :action="`${releaseUrl}/tasks`" method="post" v-slot="{ errors, processing }" class="space-y-3">
                    <select name="task_id" required :disabled="available_tasks.length === 0" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="" disabled selected>Select unassigned task</option>
                        <option v-for="task in available_tasks" :key="task.id" :value="task.id">{{ task.reference }} · {{ task.title }}</option>
                    </select>
                    <InputError :message="errors.task_id" />
                    <Button type="submit" :disabled="processing || available_tasks.length === 0"><Plus class="size-4" /> Add to release</Button>
                </Form>
            </CardContent>
        </Card>
        <p class="text-xs text-muted-foreground">Publishing releases and generating AI changelogs will be added in later stages.</p>
    </div>
</template>
