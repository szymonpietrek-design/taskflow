<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link } from '@inertiajs/vue3';
import {
    AppWindow,
    FolderKanban,
    ListTodo,
    LockKeyhole,
    Package,
    FileText,
    Settings2,
    Users,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
const { t } = useI18n();

function typeLabel(type: ProjectDetail['type']): string {
    return type === 'application' ? t('stage2.application') : t('stage2.standard');
}

function visibilityLabel(visibility: ProjectDetail['visibility']): string {
    return visibility === 'private' ? t('stage2.private') : t('stage2.workspace');
}
</script>

<template>
    <Head :title="project.name" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="min-w-0">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge variant="outline">
                    {{ typeLabel(project.type) }}
                </Badge>
                <Badge variant="outline" class="gap-1">
                    <LockKeyhole
                        v-if="project.visibility === 'private'"
                        class="size-3"
                    />
                    {{ visibilityLabel(project.visibility) }}
                </Badge>
            </div>

            <h1 class="truncate text-2xl font-semibold tracking-tight">
                {{ project.name }}
            </h1>
            <p
                v-if="project.description"
                class="mt-2 max-w-3xl text-sm text-muted-foreground"
            >
                {{ project.description }}
            </p>
            <p v-else class="mt-2 text-sm text-muted-foreground">
                {{ t('stage2.no_project_description_yet') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button as-child>
                <Link :href="`/projects/${project.id}/tasks`">
                    <ListTodo class="size-4" />
                    {{ t('stage2.view_tasks') }}
                </Link>
            </Button>
            <Button
                v-if="project.type === 'application'"
                as-child
                variant="outline"
            >
                <Link :href="`/projects/${project.id}/releases`">
                    <Package class="size-4" />
                    {{ t('stage2.releases') }}
                </Link>
            </Button>
            <Button as-child variant="outline">
                <Link :href="`/projects/${project.id}/reports`">
                    <FileText class="size-4" />
                    {{ t('stage2.reports') }}
                </Link>
            </Button>
            <Button v-if="project.can_manage" as-child variant="outline">
                <Link :href="`/projects/${project.id}/statuses`">
                    <Settings2 class="size-4" />
                    {{ t('stage2.manage_statuses') }}
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <FolderKanban class="size-4" />
                        {{ t('stage2.project_key') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{
                    project.key
                }}</CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <AppWindow class="size-4" />
                        {{ t('stage2.type') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-medium">
                    {{ typeLabel(project.type) }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Users class="size-4" />
                        {{ t('stage2.access') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-medium">
                    {{ visibilityLabel(project.visibility) }}
                </CardContent>
            </Card>
        </div>
    </div>
</template>
