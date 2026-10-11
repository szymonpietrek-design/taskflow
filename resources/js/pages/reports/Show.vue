<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, ClipboardList, FileText, LoaderCircle, MessageCircle, Activity, Trash2, Clock3, Users, ListTodo, Sparkles } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Report = {
    id: number;
    title: string;
    period_start: string;
    period_end: string;
    sources: string[];
    is_generated: boolean;
    body: string | null;
    created_by: string | null;
    created_at: string | null;
};
type WorklogGroup = {
    entries: number;
    total_minutes: number;
    creative_minutes: number;
};
type WorklogBreakdown = {
    entries: number;
    total_minutes: number;
    creative_entries: number;
    creative_minutes: number;
    by_user: (WorklogGroup & { user_id: number | null; name: string })[];
    by_task: (WorklogGroup & { task_id: number; number: number; title: string })[];
};
const props = defineProps<{
    project: { id: number; name: string; key: string };
    report: Report;
    source_counts: { tasks: number | null; messages: number | null; activity: number | null; worklogs: WorklogBreakdown | null };
    can_delete: boolean;
}>();
const worklogDetails = computed<WorklogBreakdown>(() => props.source_counts.worklogs ?? {
    entries: 0,
    total_minutes: 0,
    creative_entries: 0,
    creative_minutes: 0,
    by_user: [],
    by_task: [],
});
const baseUrl = `/projects/${props.project.id}/reports`;
const deleteForm = useForm({});
function removeReport() {
    if (!window.confirm('Delete this report draft?')) return;
    deleteForm.delete(`${baseUrl}/${props.report.id}`, { preserveScroll: true });
}
const selectedSources = [
    { key: 'tasks' as const, title: 'Tasks', icon: ClipboardList },
    { key: 'messages' as const, title: 'Messages', icon: MessageCircle },
    { key: 'activity' as const, title: 'Activity', icon: Activity },
].filter((source) => props.report.sources.includes(source.key));
function durationLabel(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    return [hours ? `${hours}h` : '', remaining ? `${remaining}m` : ''].filter(Boolean).join(' ') || '0m';
}
function percentage(part: number, total: number): number {
    return total > 0 ? Math.round((part / total) * 100) : 0;
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`${report.title} · Reports`" />
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link :href="baseUrl"><ArrowLeft class="size-4" /> All reports</Link>
            </Button>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge :variant="report.is_generated ? 'default' : 'outline'">{{ report.is_generated ? 'Generated' : 'Draft' }}</Badge>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ report.title }}</h1>
            <p class="text-sm text-muted-foreground">{{ report.period_start }} – {{ report.period_end }}<template v-if="report.created_by"> · Created by {{ report.created_by }}</template></p>
        </div>
        <Card>
            <CardHeader><CardTitle class="text-base">Selected sources</CardTitle></CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="source in selectedSources" :key="source.key" class="rounded-lg border p-4">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <component :is="source.icon" class="size-4" /> {{ source.title }}
                        </div>
                        <p class="mt-3 text-2xl font-semibold tabular-nums">{{ source_counts[source.key] ?? 0 }}</p>
                    </div>
                    <div v-if="report.sources.includes('worklogs')" class="rounded-lg border p-4">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground"><Clock3 class="size-4" /> Worklogs</div>
                        <p class="mt-3 text-2xl font-semibold tabular-nums">{{ durationLabel(source_counts.worklogs?.total_minutes ?? 0) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ source_counts.worklogs?.entries ?? 0 }} entries</p>
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">Live totals for this project and saved date range. Tasks are counted when created or completed; messages and activity by creation date; worklogs by work date. The future AI report will use the selected sources.</p>
            </CardContent>
        </Card>
        <Card v-if="source_counts.worklogs" class="gap-4">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base"><Clock3 class="size-4" /> Worklog breakdown</CardTitle>
                <p class="text-sm text-muted-foreground">Logged work within this report’s date range, including all contributors and tasks in {{ project.name }}.</p>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border p-4">
                        <p class="flex items-center gap-2 text-xs text-muted-foreground"><Sparkles class="size-4" /> Creative work</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums">{{ durationLabel(worklogDetails.creative_minutes) }}</p>
                        <p class="text-xs text-muted-foreground">{{ percentage(worklogDetails.creative_minutes, worklogDetails.total_minutes) }}% of logged time · {{ worklogDetails.creative_entries }} entries</p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="flex items-center gap-2 text-xs text-muted-foreground"><Users class="size-4" /> Contributors</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums">{{ worklogDetails.by_user.length }}</p>
                        <p class="text-xs text-muted-foreground">People with logged time</p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="flex items-center gap-2 text-xs text-muted-foreground"><ListTodo class="size-4" /> Tasks worked on</p>
                        <p class="mt-2 text-xl font-semibold tabular-nums">{{ worklogDetails.by_task.length }}</p>
                        <p class="text-xs text-muted-foreground">Tasks with entries in this period</p>
                    </div>
                </div>
                <div v-if="worklogDetails.entries === 0" class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                    No time entries for this project and reporting period.
                </div>
                <div v-else class="grid items-start gap-6 lg:grid-cols-2">
                    <section class="min-w-0 space-y-3">
                        <h3 class="text-sm font-semibold">Time by contributor</h3>
                        <div class="max-h-[26rem] space-y-4 overflow-y-auto rounded-lg border p-4">
                            <div v-for="person in worklogDetails.by_user" :key="person.user_id ?? 'former'" class="space-y-2">
                                <div class="flex min-w-0 items-start justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate font-medium" :title="person.name">{{ person.name }}</span>
                                    <span class="shrink-0 font-semibold tabular-nums">{{ durationLabel(person.total_minutes) }}</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary" :style="{ width: `${percentage(person.total_minutes, worklogDetails.total_minutes)}%` }" />
                                </div>
                                <p class="text-xs text-muted-foreground">{{ person.entries }} entries · {{ percentage(person.total_minutes, worklogDetails.total_minutes) }}% of total<span v-if="person.creative_minutes"> · {{ durationLabel(person.creative_minutes) }} creative</span></p>
                            </div>
                        </div>
                    </section>
                    <section class="min-w-0 space-y-3">
                        <h3 class="text-sm font-semibold">Time by task</h3>
                        <div class="max-h-[26rem] space-y-4 overflow-y-auto rounded-lg border p-4">
                            <div v-for="task in worklogDetails.by_task" :key="task.task_id" class="space-y-2">
                                <div class="flex min-w-0 items-start justify-between gap-3 text-sm">
                                    <Link :href="`/projects/${project.id}/tasks/${task.task_id}`" class="min-w-0 font-medium underline-offset-4 hover:underline">
                                        <span class="text-muted-foreground">{{ project.key }}-{{ task.number }}</span> · {{ task.title }}
                                    </Link>
                                    <span class="shrink-0 font-semibold tabular-nums">{{ durationLabel(task.total_minutes) }}</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary" :style="{ width: `${percentage(task.total_minutes, worklogDetails.total_minutes)}%` }" />
                                </div>
                                <p class="text-xs text-muted-foreground">{{ task.entries }} entries · {{ percentage(task.total_minutes, worklogDetails.total_minutes) }}% of total<span v-if="task.creative_minutes"> · {{ durationLabel(task.creative_minutes) }} creative</span></p>
                            </div>
                        </div>
                    </section>
                </div>
                <p class="text-xs text-muted-foreground">Time is grouped by work date, not entry creation date. Totals reflect current entries and may change if worklogs are edited.</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader><CardTitle class="flex items-center gap-2 text-base"><FileText class="size-4" /> Report content</CardTitle></CardHeader>
            <CardContent>
                <p v-if="!report.is_generated" class="text-sm text-muted-foreground">This report is a saved draft. AI generation will be available in stage 6B.</p>
                <pre v-else class="whitespace-pre-wrap break-words text-sm">{{ report.body }}</pre>
            </CardContent>
        </Card>
        <div v-if="can_delete" class="flex justify-end">
            <Button variant="outline" type="button" :disabled="deleteForm.processing" @click="removeReport">
                <LoaderCircle v-if="deleteForm.processing" class="size-4 animate-spin" /><Trash2 v-else class="size-4" /> Delete report
            </Button>
        </div>
    </div>
</template>
