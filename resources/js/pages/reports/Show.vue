<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { ArrowLeft, ClipboardList, FileText, LoaderCircle, MessageCircle, Activity, Trash2, Clock3, Users, ListTodo, Sparkles, Pencil, Save, CheckCircle2, RotateCcw } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { formatReportPeriod, formatReportTimestamp } from '@/lib/reportDates';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Report = {
    id: number;
    title: string;
    period_start: string;
    period_end: string;
    format: string;
    contributor_names: string[] | null;
    sources: string[];
    is_generated: boolean;
    generated_locale: string | null;
    generated_at: string | null;
    published_at: string | null;
    edited_at: string | null;
    updated_version: string | null;
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
    can_generate: boolean;
    can_edit: boolean;
    editable_content: string | null;
    ai_configured: boolean;
    default_ai_language: string;
    report_html: string | null;
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
const languageOptions = [
    { value: 'en', label: 'English' },
    { value: 'pl', label: 'Polski' },
    { value: 'de', label: 'Deutsch' },
    { value: 'fr', label: 'Français' },
    { value: 'es', label: 'Español' },
    { value: 'it', label: 'Italiano' },
    { value: 'pt', label: 'Português' },
    { value: 'pt-BR', label: 'Português (Brasil)' },
    { value: 'nl', label: 'Nederlands' },
    { value: 'uk', label: 'Українська' },
    { value: 'ja', label: '日本語' },
    { value: 'ko', label: '한국어' },
    { value: 'zh-Hans', label: '简体中文' },
    { value: 'zh-Hant', label: '繁體中文' },
] as const;
const defaultLanguage = props.report.generated_locale ?? props.default_ai_language;
const isPresetLanguage = (code: string): boolean => languageOptions.some((option) => option.value === code);
const selectedLanguage = ref(isPresetLanguage(defaultLanguage) ? defaultLanguage : '__other');
const customLanguage = ref(isPresetLanguage(defaultLanguage) ? '' : defaultLanguage);
const outputLanguage = computed(() => selectedLanguage.value === '__other' ? customLanguage.value.trim() : selectedLanguage.value);
const isValidLanguage = computed(() => /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i.test(outputLanguage.value) && outputLanguage.value.length <= 35);
const generateForm = useForm({ language: defaultLanguage });
function languageLabel(code: string | null): string {
    if (!code) return '';
    return languageOptions.find((language) => language.value === code)?.label ?? code;
}
const isEditing = ref(false);
const contentForm = useForm({ content: props.editable_content ?? '', version: props.report.updated_version ?? '' });
watch(() => props.report.updated_version, () => {
    if (!isEditing.value) {
        contentForm.content = props.editable_content ?? '';
        contentForm.version = props.report.updated_version ?? '';
    }
});
function startEditing() {
    if (!props.can_edit || props.report.published_at || props.editable_content === null) return;
    contentForm.clearErrors();
    contentForm.content = props.editable_content;
    contentForm.version = props.report.updated_version ?? '';
    isEditing.value = true;
}
function cancelEditing() {
    isEditing.value = false;
    contentForm.reset();
    contentForm.clearErrors();
}
function saveContent() {
    if (!contentForm.content.trim() || contentForm.processing) return;
    contentForm.put(`${baseUrl}/${props.report.id}/content`, {
        preserveScroll: true,
        onSuccess: () => { isEditing.value = false; },
        onError: (errors) => toast.error(errors.content || 'Could not save report.'),
    });
}
const publicationForm = useForm({});
function changePublication(publish: boolean) {
    if (publicationForm.processing) return;
    if (!publish && !window.confirm('Move this report back to draft? It can then be edited or regenerated.')) return;
    publicationForm.post(`${baseUrl}/${props.report.id}/${publish ? 'publish' : 'unpublish'}`, {
        preserveScroll: true,
        onError: (errors) => toast.error(errors.publication || 'Could not update report status.'),
    });
}
const generationError = ref<string | undefined>();
function generateReport() {
    if (!props.can_generate || isEditing.value || !isValidLanguage.value || generateForm.processing) return;
    if (props.report.is_generated && !window.confirm('Replace the current report with a new version?')) return;
    generationError.value = undefined;
    generateForm.language = outputLanguage.value;
    generateForm.post(`${baseUrl}/${props.report.id}/generate`, {
        preserveScroll: true,
        onError: (errors) => {
            generationError.value = errors.ai_generation;
            toast.error(generationError.value || errors.language || 'Could not generate the report.');
        },
        onSuccess: () => { generationError.value = undefined; },
    });
}
const deleteForm = useForm({});
function removeReport() {
    if (!window.confirm('Delete this report?')) return;
    deleteForm.delete(`${baseUrl}/${props.report.id}`, { preserveScroll: true });
}
const selectedSources = [
    { key: 'tasks' as const, title: 'Tasks', icon: ClipboardList },
    { key: 'messages' as const, title: 'Messages', icon: MessageCircle },
    { key: 'activity' as const, title: 'Activity', icon: Activity },
].filter((source) => props.report.sources.includes(source.key));
const selectedSourceCount = selectedSources.length + Number(props.report.sources.includes('worklogs'));
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
                <Badge variant="outline">{{ report.format === 'work_report' ? 'Detailed report' : 'Summary' }}</Badge>
                <Badge :variant="report.published_at ? 'default' : 'outline'">{{ report.published_at ? 'Published' : 'Draft' }}</Badge>
                <Badge v-if="report.is_generated && !report.published_at" variant="secondary">Generated</Badge>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ report.title }}</h1>
            <p class="text-sm text-muted-foreground">{{ formatReportPeriod(report.period_start, report.period_end) }}<template v-if="report.created_by"> · Created by {{ report.created_by }}</template></p>
            <p class="text-xs text-muted-foreground">People: {{ report.contributor_names === null ? 'All contributors' : (report.contributor_names.join(', ') || 'Former contributors') }}</p>
        </div>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between gap-3">
                <CardTitle class="text-base">Sources</CardTitle>
                <span class="text-xs text-muted-foreground">{{ selectedSourceCount }} selected</span>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));">
                    <div v-for="source in selectedSources" :key="source.key" class="min-w-0 rounded-lg border bg-card p-4">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <component :is="source.icon" class="size-4" /> {{ source.title }}
                        </div>
                        <p class="mt-3 text-2xl font-semibold tabular-nums">{{ source_counts[source.key] ?? 0 }}</p>
                    </div>
                    <div v-if="report.sources.includes('worklogs')" class="min-w-0 rounded-lg border bg-card p-4">
                        <div class="flex items-center gap-2 text-sm text-muted-foreground"><Clock3 class="size-4" /> Worklogs</div>
                        <p class="mt-3 text-2xl font-semibold tabular-nums">{{ durationLabel(source_counts.worklogs?.total_minutes ?? 0) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ source_counts.worklogs?.entries ?? 0 }} entries</p>
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">Totals update as project data changes.</p>
            </CardContent>
        </Card>
        <Card v-if="source_counts.worklogs" class="gap-4">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base"><Clock3 class="size-4" /> Worklog breakdown</CardTitle>
                <p class="text-sm text-muted-foreground">Time recorded for this project during the selected period.</p>
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
                <p class="text-xs text-muted-foreground">Based on work dates. Totals update when entries change.</p>
            </CardContent>
        </Card>
        <div class="grid min-w-0 items-start gap-6" :class="can_generate ? 'xl:grid-cols-[minmax(0,1fr)_19rem]' : ''">
            <Card class="min-w-0">
                <CardHeader class="gap-2">
                    <CardTitle class="flex items-center gap-2 text-base"><FileText class="size-4" /> Report</CardTitle>
                    <p v-if="report.is_generated && report.generated_at" class="text-xs text-muted-foreground">
                        Generated {{ formatReportTimestamp(report.generated_at) }}<template v-if="report.generated_locale"> · {{ languageLabel(report.generated_locale) }}</template><template v-if="report.edited_at"> · Edited {{ formatReportTimestamp(report.edited_at) }}</template>
                    </p>
                    <p v-if="report.published_at" class="text-xs text-muted-foreground">Published {{ formatReportTimestamp(report.published_at) }}</p>
                    <p v-if="report.format === 'work_report' && report.edited_at && !isEditing" class="text-xs text-muted-foreground">This document has been edited. Source worklogs and the live statistics above are unchanged.</p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="can_edit && report.is_generated" class="flex flex-wrap items-center justify-between gap-2 border-b pb-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <Button v-if="!isEditing && !report.published_at && editable_content !== null" type="button" size="sm" variant="outline" @click="startEditing">
                                <Pencil class="size-4" /> Edit
                            </Button>
                            <template v-if="isEditing">
                                <Button type="button" size="sm" variant="outline" :disabled="contentForm.processing" @click="cancelEditing">Cancel</Button>
                                <Button type="button" size="sm" :disabled="contentForm.processing || !contentForm.content.trim()" @click="saveContent">
                                    <LoaderCircle v-if="contentForm.processing" class="size-4 animate-spin" /><Save v-else class="size-4" /> Save changes
                                </Button>
                            </template>
                        </div>
                        <Button v-if="!isEditing && !report.published_at" type="button" size="sm" :disabled="publicationForm.processing" @click="changePublication(true)">
                            <LoaderCircle v-if="publicationForm.processing" class="size-4 animate-spin" /><CheckCircle2 v-else class="size-4" /> Publish
                        </Button>
                        <Button v-if="!isEditing && report.published_at" type="button" size="sm" variant="outline" :disabled="publicationForm.processing" @click="changePublication(false)">
                            <LoaderCircle v-if="publicationForm.processing" class="size-4 animate-spin" /><RotateCcw v-else class="size-4" /> Move to draft
                        </Button>
                    </div>
                    <div v-if="isEditing" class="space-y-3">
                        <p class="text-sm text-muted-foreground">Edit the full report as Markdown, including headings, table rows and totals. Changes affect this document only — source tasks and worklogs stay unchanged.</p>
                        <Textarea v-model="contentForm.content" class="min-h-[26rem] resize-y font-mono text-sm leading-6" :maxlength="50000" aria-label="Report content" />
                        <p class="text-xs text-muted-foreground">{{ contentForm.content.length.toLocaleString() }} characters · Markdown supported</p>
                        <InputError :message="contentForm.errors.content || contentForm.errors.version" />
                    </div>
                    <div v-else-if="report.is_generated && report_html" class="report-markdown max-w-none overflow-x-auto break-words text-sm leading-6 sm:text-[0.9375rem] sm:leading-7 [&_a]:underline [&_h1]:mb-4 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mb-3 [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:my-3 [&_h3]:font-semibold [&_ul]:my-3 [&_ul]:list-disc [&_ol]:my-3 [&_ol]:list-decimal [&_li]:ml-5 [&_li]:my-1 [&_p]:mb-4 [&_strong]:font-semibold [&_code]:font-mono [&_table]:my-5 [&_table]:w-full [&_table]:min-w-[42rem] [&_table]:border-collapse [&_thead]:bg-muted/60 [&_th]:border [&_th]:p-2 [&_th]:text-left [&_td]:border [&_td]:p-2 [&_td]:align-top [&_tbody_tr:nth-child(even)]:bg-muted/20" v-html="report_html"></div>
                    <div v-else class="rounded-lg border border-dashed px-4 py-10 text-center">
                        <FileText class="mx-auto mb-3 size-7 text-muted-foreground" />
                        <p class="text-sm font-medium">No report content yet</p>
                        <p class="mt-1 text-sm text-muted-foreground">Generate a report from the selected sources.</p>
                    </div>
                </CardContent>
            </Card>
            <Card v-if="can_generate" class="min-w-0 xl:sticky xl:top-6">
                <CardHeader class="gap-1">
                    <CardTitle class="flex items-center gap-2 text-base"><Sparkles class="size-4" /> Generate</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ report.format === 'work_report' ? 'Generate a work summary and time register.' : 'Summarize progress from the selected sources.' }}</p>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="generateReport">
                        <div class="space-y-1.5">
                            <label for="report-ai-language" class="text-sm font-medium">Language</label>
                            <Select v-model="selectedLanguage">
                                <SelectTrigger id="report-ai-language" class="w-full"><SelectValue placeholder="Choose a language" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="language in languageOptions" :key="language.value" :value="language.value">{{ language.label }}</SelectItem>
                                    <SelectItem value="__other">Other language…</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="selectedLanguage === '__other'" class="space-y-1.5">
                            <label for="report-ai-custom-language" class="text-sm font-medium">Language code</label>
                            <Input id="report-ai-custom-language" v-model="customLanguage" maxlength="35" placeholder="e.g. sv, hi, zh-Hant-TW" />
                        </div>
                        <InputError :message="generateForm.errors.language || generationError" />
                        <Button type="submit" class="w-full" :disabled="!can_generate || isEditing || generateForm.processing || !ai_configured || !isValidLanguage">
                            <LoaderCircle v-if="generateForm.processing" class="size-4 animate-spin" />
                            <Sparkles v-else class="size-4" />
                            {{ generateForm.processing ? 'Generating…' : (report.is_generated ? 'Regenerate report' : 'Generate report') }}
                        </Button>
                        <p v-if="!ai_configured" class="text-xs text-muted-foreground">Report generation is not configured.</p>
                        <p v-else class="text-xs text-muted-foreground">{{ report.published_at ? 'Move this report to draft before regenerating it.' : 'Selected project data is sent to your AI provider. Review the result before sharing.' }}</p>
                    </form>
                </CardContent>
            </Card>
        </div>
        <div v-if="can_delete" class="flex justify-end">
            <Button variant="outline" type="button" :disabled="deleteForm.processing" @click="removeReport">
                <LoaderCircle v-if="deleteForm.processing" class="size-4 animate-spin" /><Trash2 v-else class="size-4" /> Delete report
            </Button>
        </div>
    </div>
</template>
