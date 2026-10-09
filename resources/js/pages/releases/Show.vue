<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Clock3, Package } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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

const props = defineProps<{
    project: { id: number; name: string; key: string };
    release: Release;
}>();

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
        <p class="text-xs text-muted-foreground">This stage creates draft releases. Scheduling, publishing, task assignment and AI changelogs come next.</p>
    </div>
</template>
