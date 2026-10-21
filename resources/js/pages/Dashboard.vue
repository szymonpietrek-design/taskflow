<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight, CalendarClock, CheckCircle2, CircleAlert, Clock3,
    ListTodo, MessageSquare, Activity,
} from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TaskStatusIcon from '@/components/tasks/TaskStatusIcon.vue';
import TaskPriorityLabel from '@/components/tasks/TaskPriorityLabel.vue';
import { dashboardCopy } from './dashboardCopy';
import { taskStatusBadgeClass } from '@/lib/taskVisuals';
import { dashboard } from '@/routes';
import type { TaskPriorityValue, TaskStatusSummary } from '@/types/task';

type TaskSummary = {
    id: number;
    project_id: number;
    reference: string;
    title: string;
    status: string;
    priority: TaskPriorityValue;
    category: TaskStatusSummary['category'];
    due_date: string | null;
    overdue: boolean;
};
type ActivityKind = 'status' | 'priority' | 'assignee' | 'due_date' | 'release' | 'title' | 'description' | 'type' | 'comment';
type ActivityChange = { kind: ActivityKind };
type ActivityItem = {
    id: string;
    kind: ActivityKind;
    project_id: number;
    task_id: number;
    reference: string;
    task_title: string;
    actor: string | null;
    created_at: string | null;
    changes: ActivityChange[];
};

const props = defineProps<{
    workspace_name: string | null;
    stats: { projects: number; my_open_tasks: number; overdue: number; due_soon: number; week_minutes: number };
    my_tasks: TaskSummary[];
    task_view: 'attention' | 'open' | 'overdue' | 'due_soon';
    task_pagination: { previous_url: string | null; next_url: string | null };
    activity: ActivityItem[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

const { t, locale } = useI18n();
const dateLocale = computed(() => locale.value === 'pl' ? 'pl-PL' : 'en-US');
const copy = computed(() => locale.value === 'pl' ? dashboardCopy.pl : dashboardCopy.en);
const formattedDate = (value: string | null): string => {
    if (!value) return '';
    const date = new Date(value.includes('T') ? value : `${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(dateLocale.value, { day: 'numeric', month: 'short', ...(value.includes('T') ? { hour: '2-digit', minute: '2-digit' } : {}) }).format(date);
};
const taskUrl = (projectId: number, taskId: number): string => `/projects/${projectId}/tasks/${taskId}`;
const activityLabel = (kind: ActivityItem['kind']): string => copy.value[kind];
const activitySummary = (item: ActivityItem): string => {
    const count = item.changes.length;
    if (count <= 1) return activityLabel(item.kind);
    if (locale.value !== 'pl') return copy.value.changeCount.replace('{count}', String(count));
    const polishForm = count % 10 >= 2 && count % 10 <= 4 && (count % 100 < 12 || count % 100 > 14);
    return (polishForm ? copy.value.changeCountFew : copy.value.changeCount).replace('{count}', String(count));
};
const formatMinutes = (minutes: number): string => {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return hours > 0 ? `${hours} h${remainder ? ` ${remainder} min` : ''}` : `${remainder} min`;
};
const taskListSubtitle = computed(() => ({
    attention: copy.value.attentionHelp,
    open: copy.value.focusOpen,
    overdue: copy.value.focusOverdue,
    due_soon: copy.value.focusDueSoon,
})[props.task_view]);
</script>

<template>
    <Head :title="t('home.title')" />

    <div class="mx-auto flex w-full min-w-0 max-w-[90rem] flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-1.5">
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('home.title') }}</h1>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ workspace_name ? t('home.workspaceIntro').replace('{workspace}', workspace_name) : t('home.emptyIntro') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link href="/worklogs"><Clock3 class="size-4" /> {{ copy.logTime }}</Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/projects">{{ t('home.allProjects') }} <ArrowRight class="size-4" /></Link>
                </Button>
            </div>
        </header>

        <section :aria-label="t('home.summary')" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Link href="/worklogs" class="block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <Card class="h-full gap-3 py-4 shadow-none transition-colors hover:bg-muted/40">
                    <CardContent class="space-y-3 px-4 sm:px-5">
                        <Clock3 class="size-4 text-muted-foreground" />
                        <div class="text-2xl font-semibold tabular-nums">{{ formatMinutes(stats.week_minutes) }}</div>
                        <p class="text-xs text-muted-foreground">{{ copy.weekTime }}</p>
                        <p v-if="!stats.week_minutes" class="text-xs text-muted-foreground">{{ copy.noTimeThisWeek }}</p>
                    </CardContent>
                </Card>
            </Link>
            <Link href="/dashboard?focus=open#attention" class="block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <Card :class="task_view === 'open' ? 'ring-1 ring-ring/40' : ''" class="h-full gap-3 py-4 shadow-none transition-colors hover:bg-muted/40">
                    <CardContent class="space-y-3 px-4 sm:px-5">
                        <ListTodo class="size-4 text-muted-foreground" />
                        <div class="text-2xl font-semibold tabular-nums">{{ stats.my_open_tasks }}</div>
                        <p class="text-xs text-muted-foreground">{{ t('home.myOpenTasks') }}</p>
                    </CardContent>
                </Card>
            </Link>
            <Link href="/dashboard?focus=due_soon#attention" class="block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <Card :class="task_view === 'due_soon' ? 'ring-1 ring-ring/40' : ''" class="h-full gap-3 py-4 shadow-none transition-colors hover:bg-muted/40">
                    <CardContent class="space-y-3 px-4 sm:px-5">
                        <CalendarClock class="size-4 text-muted-foreground" />
                        <div class="text-2xl font-semibold tabular-nums">{{ stats.due_soon }}</div>
                        <p class="text-xs text-muted-foreground">{{ t('home.dueSoon') }}</p>
                    </CardContent>
                </Card>
            </Link>
            <Link href="/dashboard?focus=overdue#attention" class="block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <Card :class="task_view === 'overdue' ? 'ring-1 ring-ring/40' : ''" class="h-full gap-3 py-4 shadow-none transition-colors hover:bg-muted/40">
                    <CardContent class="space-y-3 px-4 sm:px-5">
                        <CircleAlert :class="stats.overdue ? 'text-destructive' : 'text-muted-foreground'" class="size-4" />
                        <div :class="stats.overdue ? 'text-destructive' : ''" class="text-2xl font-semibold tabular-nums">{{ stats.overdue }}</div>
                        <p class="text-xs text-muted-foreground">{{ t('home.overdue') }}</p>
                    </CardContent>
                </Card>
            </Link>
        </section>

        <div class="grid min-w-0 items-start gap-4 lg:grid-cols-2">
            <Card id="attention" class="min-w-0 scroll-mt-6 gap-0 py-0 shadow-none">
                <CardHeader class="flex flex-row items-center justify-between gap-3 border-b py-4">
                    <div class="space-y-1">
                        <CardTitle class="text-base">{{ copy.needsAttention }}</CardTitle>
                        <p class="text-xs text-muted-foreground">{{ taskListSubtitle }}</p>
                    </div>
                    <Link v-if="task_view !== 'attention'" href="/dashboard#attention" class="shrink-0 text-xs font-medium text-muted-foreground hover:text-foreground hover:underline">
                        {{ copy.clearTaskFocus }}
                    </Link>
                    <ListTodo v-else class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent class="px-0 py-0">
                    <div v-if="my_tasks.length" class="divide-y">
                        <Link v-for="task in my_tasks" :key="task.id" :href="taskUrl(task.project_id, task.id)"
                            class="flex min-w-0 items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:px-5">
                            <TaskStatusIcon :category="task.category" />
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="truncate text-sm font-medium">{{ task.title }}</div>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                    <span>{{ task.reference }}</span>
                                    <TaskPriorityLabel v-if="task.priority >= 3" :priority="task.priority" />
                                    <span v-if="task.due_date" class="inline-flex items-center gap-1" :class="task.overdue ? 'font-medium text-destructive' : ''">
                                        <Clock3 class="size-3" /> {{ formattedDate(task.due_date) }}
                                    </span>
                                </div>
                            </div>
                            <Badge variant="outline" :class="taskStatusBadgeClass(task.category)" class="max-w-32 shrink-0 truncate text-[11px]">
                                {{ task.status }}
                            </Badge>
                            <ArrowRight class="size-3.5 shrink-0 text-muted-foreground" />
                        </Link>
                    </div>
                    <div v-else class="flex flex-col items-center gap-3 px-5 py-12 text-center">
                        <CheckCircle2 class="size-8 text-muted-foreground/60" />
                        <p class="font-medium">{{ task_view === 'attention' ? t('home.noMyTasks') : copy.noMatchingTasks }}</p>
                        <p class="max-w-sm text-sm text-muted-foreground">{{ task_view === 'attention' ? t('home.noMyTasksHelp') : copy.noMatchingTasksHelp }}</p>
                    </div>
                    <div v-if="task_view === 'attention' && task_pagination.next_url" class="border-t px-4 py-3 sm:px-5">
                        <Link href="/dashboard?focus=open#attention" class="inline-flex items-center gap-1 text-sm font-medium hover:underline">
                            {{ copy.seeAllTasks }} <ArrowRight class="size-3.5" />
                        </Link>
                    </div>
                    <div v-else-if="task_view !== 'attention' && (task_pagination.previous_url || task_pagination.next_url)" class="flex items-center justify-between gap-3 border-t px-4 py-3 sm:px-5">
                        <Link v-if="task_pagination.previous_url" :href="`${task_pagination.previous_url}#attention`" class="text-sm font-medium hover:underline">{{ copy.previousPage }}</Link>
                        <span v-else />
                        <Link v-if="task_pagination.next_url" :href="`${task_pagination.next_url}#attention`" class="inline-flex items-center gap-1 text-sm font-medium hover:underline">{{ copy.nextPage }} <ArrowRight class="size-3.5" /></Link>
                    </div>
                </CardContent>
            </Card>

            <Card class="min-w-0 gap-0 py-0 shadow-none">
                <CardHeader class="flex flex-row items-center justify-between gap-3 border-b py-4">
                    <div class="space-y-1">
                        <CardTitle class="text-base">{{ t('home.recentActivity') }}</CardTitle>
                        <p class="text-xs text-muted-foreground">{{ copy.activityHelp }}</p>
                    </div>
                    <Activity class="size-4 shrink-0 text-muted-foreground" />
                </CardHeader>
                <CardContent class="px-0 py-0">
                    <div v-if="activity.length" class="divide-y">
                        <Link v-for="item in activity" :key="item.id" :href="taskUrl(item.project_id, item.task_id)"
                            class="flex min-w-0 items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:px-5">
                            <MessageSquare v-if="item.kind === 'comment'" class="size-4 shrink-0 text-muted-foreground" />
                            <Activity v-else class="size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex min-w-0 items-center gap-2">
                                    <p class="min-w-0 flex-1 truncate text-sm">
                                        <span class="font-medium">{{ item.actor || t('home.unknownActor') }}</span>
                                        <span class="text-muted-foreground"> · {{ activitySummary(item) }}</span>
                                    </p>
                                    <time v-if="item.created_at" :datetime="item.created_at" class="shrink-0 text-xs text-muted-foreground">{{ formattedDate(item.created_at) }}</time>
                                </div>
                                <p class="truncate text-xs text-muted-foreground">
                                    <span>{{ item.reference }} · </span><span class="font-medium text-foreground">{{ item.task_title }}</span>
                                </p>
                            </div>
                            <ArrowRight class="size-3.5 shrink-0 text-muted-foreground" />
                        </Link>
                    </div>
                    <p v-else class="px-5 py-8 text-center text-sm text-muted-foreground">{{ t('home.noActivity') }}</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
