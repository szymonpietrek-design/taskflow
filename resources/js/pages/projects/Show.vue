<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AppWindow, FolderKanban, LockKeyhole, Users } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ProjectDetail } from '@/types';

defineProps<{
    project: ProjectDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Projects',
                href: '/projects',
            },
        ],
    },
});
</script>

<template>
    <Head :title="project.name" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="min-w-0">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge variant="outline">
                    {{ project.type === 'application' ? 'Application' : 'Standard' }}
                </Badge>
                <Badge variant="outline" class="gap-1">
                    <LockKeyhole v-if="project.visibility === 'private'" class="size-3" />
                    {{ project.visibility === 'private' ? 'Private' : 'Workspace' }}
                </Badge>
            </div>

            <h1 class="truncate text-2xl font-semibold tracking-tight">{{ project.name }}</h1>
            <p v-if="project.description" class="mt-2 max-w-3xl text-sm text-muted-foreground">
                {{ project.description }}
            </p>
            <p v-else class="mt-2 text-sm text-muted-foreground">No project description yet.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium">
                        <FolderKanban class="size-4" />
                        Project key
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ project.key }}</CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium">
                        <AppWindow class="size-4" />
                        Type
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-medium">
                    {{ project.type === 'application' ? 'Application' : 'Standard' }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-medium">
                        <Users class="size-4" />
                        Access
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-medium">
                    {{ project.visibility === 'private' ? 'Private' : 'Workspace' }}
                </CardContent>
            </Card>
        </div>
    </div>
</template>
