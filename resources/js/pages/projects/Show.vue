<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Activity,
    Archive,
    ArrowUpRight,
    CalendarDays,
    CheckCircle2,
    Clock3,
    FileText,
    FolderKanban,
    History,
    ListTodo,
    LockKeyhole,
    MessageSquare,
    Package,
    Plus,
} from '@lucide/vue';
import CreateTaskDialog from '@/components/tasks/CreateTaskDialog.vue';
import TaskStatusIcon from '@/components/tasks/TaskStatusIcon.vue';
import { taskStatusBadgeClass } from '@/lib/taskVisuals';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ProjectDetail } from '@/types';
import type { TaskParentSummary, TaskStatusSummary, TaskUserSummary } from '@/types/task';

type RecentTask = {
    id: number;
    reference: string;
    title: string;
    status: string;
    category: 'todo' | 'in_progress' | 'done' | 'canceled';
};

type TimelineItem = {
    id: string;
    kind: 'status' | 'change' | 'comment' | 'worklog';
    task_id: number;
    reference: string;
    actor: string | null;
    minutes: number | null;
    created_at: string | null;
};

type ProjectOverview = {
    stats: {
        total: number;
        in_progress: number;
        completed: number;
        worklog_minutes: number;
        progress_percent: number;
    };
    recent_tasks: RecentTask[];
    activity: TimelineItem[];
    latest_release: {
        id: number;
        version: string;
        name: string | null;
        released_at: string | null;
    } | null;
};

const props = defineProps<{
    project: ProjectDetail;
    can_create_task: boolean;
    overview: ProjectOverview;
    task_form: {
        statuses: TaskStatusSummary[];
        assignees: TaskUserSummary[];
        parent_tasks: TaskParentSummary[];
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: '/projects' }],
    },
});

const { t, locale, displayLabel } = useI18n();
const isCreateDialogOpen = ref(false);
const projectUrl = computed(() => `/projects/${props.project.id}`);
const taskUrl = (id: number) => `${projectUrl.value}/tasks/${id}`;
const progress = computed(() => Math.min(100, Math.max(0, props.overview.stats.progress_percent)));
const formatter = computed(() => new Intl.DateTimeFormat(locale.value === 'pl' ? 'pl-PL' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
}));

function typeLabel(type: ProjectDetail['type']): string {
    return type === 'application' ? t('stage2.application') : t('stage2.standard');
}

function visibilityLabel(visibility: ProjectDetail['visibility']): string {
    return visibility === 'private' ? t('stage2.private') : t('stage2.workspace');
}

function formatMinutes(total: number): string {
    const hours = Math.floor(total / 60);
    const minutes = total % 60;
    if (hours === 0) return `${minutes} min`;
    return minutes ? `${hours} h ${minutes} min` : `${hours} h`;
}

function formatDate(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : formatter.value.format(date);
}

function activityLabel(kind: TimelineItem['kind']): string {
    return t(`overview.activity.${kind}`);
}

</script>

<template>
    <Head :title="project.name" />

    <div class="mx-auto flex w-full min-w-0 max-w-6xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 md:gap-7 lg:px-8">
        <div v-if="project.is_archived" class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
            <Archive class="size-4 shrink-0" />
            {{ t('workspaceProjects.archivedNotice') }}
            <Link href="/workspaces" class="font-medium underline underline-offset-2">{{ t('stage2.workspaces') }}</Link>
        </div>

        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border bg-muted/50 sm:size-12">
                    <FolderKanban class="size-5 text-foreground" />
                </div>
                <div class="min-w-0 space-y-2">
                    <div>
                        <h1 class="break-words text-2xl font-semibold tracking-tight">{{ project.name }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <Badge variant="secondary">{{ project.key }}</Badge>
                            <Badge variant="outline">{{ typeLabel(project.type) }}</Badge>
                            <Badge variant="outline" class="gap-1">
                                <LockKeyhole v-if="project.visibility === 'private'" class="size-3" />
                                {{ visibilityLabel(project.visibility) }}
                            </Badge>
                        </div>
                    </div>
                    <p class="max-w-3xl text-sm leading-relaxed text-muted-foreground">
                        {{ project.description || t('stage2.no_project_description_yet') }}
                    </p>
                </div>
            </div>
            <Button v-if="can_create_task && !project.is_archived && task_form" type="button" class="shrink-0 self-start" @click="isCreateDialogOpen = true">
                <Plus class="size-4" /> {{ t('overview.newTask') }}
            </Button>
        </section>

        <nav class="flex flex-wrap gap-2" :aria-label="t('overview.projectNavigation')">
            <Button variant="outline" size="sm" as-child>
                <Link :href="`${projectUrl}/tasks`"><ListTodo class="size-4" /> {{ t('stage2.tasks') }}</Link>
            </Button>
            <Button v-if="project.type === 'application'" variant="outline" size="sm" as-child>
                <Link :href="`${projectUrl}/releases`"><Package class="size-4" /> {{ t('stage2.releases') }}</Link>
            </Button>
            <Button variant="outline" size="sm" as-child>
                <Link :href="`${projectUrl}/reports`"><FileText class="size-4" /> {{ t('stage2.reports') }}</Link>
            </Button>
        </nav>

        <section :aria-label="t('overview.statistics')" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Card class="gap-3 py-4 shadow-none">
                <CardContent class="space-y-3 px-4 sm:px-5">
                    <ListTodo class="size-4 text-muted-foreground" />
                    <div class="text-2xl font-semibold tabular-nums">{{ overview.stats.total }}</div>
                    <div class="text-xs text-muted-foreground">{{ t('overview.totalTasks') }}</div>
                </CardContent>
            </Card>
            <Card class="gap-3 py-4 shadow-none">
                <CardContent class="space-y-3 px-4 sm:px-5">
                    <Clock3 class="size-4 text-muted-foreground" />
                    <div class="text-2xl font-semibold tabular-nums">{{ overview.stats.in_progress }}</div>
                    <div class="text-xs text-muted-foreground">{{ t('overview.inProgress') }}</div>
                </CardContent>
            </Card>
            <Card class="gap-3 py-4 shadow-none">
                <CardContent class="space-y-3 px-4 sm:px-5">
                    <CheckCircle2 class="size-4 text-muted-foreground" />
                    <div class="text-2xl font-semibold tabular-nums">{{ overview.stats.completed }}</div>
                    <div class="text-xs text-muted-foreground">{{ t('overview.completed') }}</div>
                </CardContent>
            </Card>
            <Card class="gap-3 py-4 shadow-none">
                <CardContent class="space-y-3 px-4 sm:px-5">
                    <History class="size-4 text-muted-foreground" />
                    <div class="text-2xl font-semibold tabular-nums">{{ formatMinutes(overview.stats.worklog_minutes) }}</div>
                    <div class="text-xs text-muted-foreground">{{ t('overview.workTime') }}</div>
                </CardContent>
            </Card>
        </section>

        <section class="space-y-2" :aria-label="t('overview.progress')">
            <div class="flex justify-between text-xs">
                <span class="font-medium">{{ t('overview.progress') }}</span>
                <span class="tabular-nums text-muted-foreground">{{ progress }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-label="t('overview.progress')" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
            </div>
            <p class="text-xs text-muted-foreground">{{ t('overview.progressHelp') }}</p>
        </section>

        <div class="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] xl:gap-5">
            <div class="grid min-w-0 content-start gap-4">
                <Card class="gap-0 py-0 shadow-none">
                    <CardHeader class="flex flex-row items-center justify-between gap-3 border-b py-4">
                        <CardTitle class="text-base">{{ t('overview.recentTasks') }}</CardTitle>
                        <Link :href="`${projectUrl}/tasks`" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                            {{ t('overview.viewAll') }} <ArrowUpRight class="size-3.5" />
                        </Link>
                    </CardHeader>
                    <CardContent class="px-0 py-1">
                        <div v-if="!overview.recent_tasks.length" class="px-5 py-9 text-center text-sm text-muted-foreground">
                            {{ t('overview.noTasks') }}
                        </div>
                        <Link
                            v-for="task in overview.recent_tasks"
                            :key="task.id"
                            :href="taskUrl(task.id)"
                            class="flex items-start justify-between gap-3 border-b px-4 py-3 transition-colors last:border-b-0 hover:bg-muted/40 sm:px-5"
                        >
                            <div class="flex min-w-0 items-start gap-2.5">
                                <span class="mt-0.5 shrink-0"><TaskStatusIcon :category="task.category" /></span>
                                <div class="min-w-0 space-y-1">
                                    <p class="break-words text-sm font-medium">{{ task.title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ task.reference }}</p>
                                </div>
                            </div>
                            <Badge variant="outline" :class="[taskStatusBadgeClass(task.category), 'max-w-36 shrink-0 text-center text-[11px] font-normal whitespace-normal']">
                                {{ displayLabel(task.status) }}
                            </Badge>
                        </Link>
                    </CardContent>
                </Card>

                <Card v-if="project.type === 'application'" class="gap-0 py-0 shadow-none">
                    <CardHeader class="flex flex-row items-center justify-between gap-2 border-b py-4">
                        <CardTitle class="text-base">{{ t('overview.latestRelease') }}</CardTitle>
                        <Package class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent class="py-5">
                        <div v-if="overview.latest_release" class="space-y-2">
                            <Link :href="`${projectUrl}/releases/${overview.latest_release.id}`" class="inline-flex items-center gap-1.5 text-lg font-semibold hover:underline">
                                v{{ overview.latest_release.version }} <ArrowUpRight class="size-4 text-muted-foreground" />
                            </Link>
                            <p v-if="overview.latest_release.name" class="text-sm">{{ overview.latest_release.name }}</p>
                            <p v-if="formatDate(overview.latest_release.released_at)" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarDays class="size-3.5" /> {{ formatDate(overview.latest_release.released_at) }}
                            </p>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ t('overview.noRelease') }}</p>
                        <Link :href="`${projectUrl}/releases`" class="mt-3 inline-flex items-center gap-1 text-xs font-medium hover:underline">
                            {{ t('overview.viewReleases') }} <ArrowUpRight class="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <Card class="gap-0 py-0 shadow-none">
                <CardHeader class="flex flex-row items-center justify-between gap-2 border-b py-4">
                    <CardTitle class="text-base">{{ t('overview.recentActivity') }}</CardTitle>
                    <Activity class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent class="px-0 py-1">
                    <p v-if="!overview.activity.length" class="px-5 py-9 text-center text-sm text-muted-foreground">
                        {{ t('overview.noActivity') }}
                    </p>
                    <div v-for="item in overview.activity" :key="item.id" class="flex gap-3 border-b px-4 py-3 last:border-b-0 sm:px-5">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted">
                            <MessageSquare v-if="item.kind === 'comment'" class="size-4 text-muted-foreground" />
                            <Clock3 v-else-if="item.kind === 'worklog'" class="size-4 text-muted-foreground" />
                            <CheckCircle2 v-else-if="item.kind === 'status'" class="size-4 text-muted-foreground" />
                            <History v-else class="size-4 text-muted-foreground" />
                        </div>
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-medium">
                                {{ activityLabel(item.kind) }}<template v-if="item.kind === 'worklog' && item.minutes !== null"> · {{ formatMinutes(item.minutes) }}</template>
                            </p>
                            <Link :href="taskUrl(item.task_id)" class="text-xs font-medium underline-offset-2 hover:underline">{{ item.reference }}</Link>
                            <p class="text-xs text-muted-foreground">
                                {{ item.actor || t('overview.unknownActor') }}<template v-if="formatDate(item.created_at)"> · {{ formatDate(item.created_at) }}</template>
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <CreateTaskDialog
            v-if="can_create_task && !project.is_archived && task_form"
            v-model:open="isCreateDialogOpen"
            :project-id="project.id"
            :statuses="task_form.statuses"
            :assignees="task_form.assignees"
            :parent-tasks="task_form.parent_tasks"
        />
    </div>
</template>
