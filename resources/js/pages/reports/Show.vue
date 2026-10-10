<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, FileText, LoaderCircle, MessageCircle, Activity, Trash2 } from '@lucide/vue';
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
const props = defineProps<{
    project: { id: number; name: string; key: string };
    report: Report;
    source_counts: { tasks: number | null; messages: number | null; activity: number | null };
    can_delete: boolean;
}>();
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
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`${report.title} · Reports`" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
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
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="source in selectedSources" :key="source.key" class="rounded-lg border p-4">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <component :is="source.icon" class="size-4" /> {{ source.title }}
                        </div>
                        <p class="mt-3 text-2xl font-semibold tabular-nums">{{ source_counts[source.key] ?? 0 }}</p>
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">Live counts for the saved date range. Tasks are counted when created or completed; messages and activity are counted by their creation date. The future AI report will use the selected project sources.</p>
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
