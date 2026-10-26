<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { ArrowLeft, ExternalLink, GitBranch, GitMerge, History, RefreshCw, FileDiff, Sparkles } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import '@/components/tasks/task-markdown-typography.css';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type LinkedUser = { id: number; name: string } | null;
type Participant = { login: string | null; taskflow_user: LinkedUser };
type PullRequest = {
    number: number;
    title: string;
    body_html: string;
    web_url: string;
    state: 'open' | 'closed' | 'merged';
    is_draft: boolean;
    source_branch: string | null;
    target_branch: string | null;
    source_repository: string | null;
    head_sha: string | null;
    base_sha: string | null;
    merge_commit_sha: string | null;
    author: Participant;
    merged_by: Participant | null;
    remote_created_at: string | null;
    remote_updated_at: string | null;
    closed_at: string | null;
    merged_at: string | null;
    synced_at: string | null;
    reviews_checked_at: string | null;
};
type ChangedFile = {
    path: string;
    previous_path: string | null;
    status: string;
    additions: number;
    deletions: number;
    patch: string | null;
    patch_truncated: boolean;
};
type Review = {
    id: number;
    state: 'approved' | 'changes_requested' | 'commented' | 'dismissed';
    reviewer_login: string | null;
    taskflow_reviewer: LinkedUser;
    body: string | null;
    submitted_at: string | null;
};
type AiHistoryEntry = {
    id: number;
    version: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    language: string;
    source_head_sha: string;
    include_patch_excerpts: boolean;
    ai_provider: string | null;
    ai_model: string | null;
    prompt_tokens: number | null;
    completion_tokens: number | null;
    estimated_cost_usd: string | null;
    created_at: string | null;
    finished_at: string | null;
    result_html: string;
};
type AiQuota = { used_24h: number; used_1h: number; limit_24h: number; limit_1h: number; allowed: boolean };
type AiUsage = { completed: number; failed: number; unknown_cost: number; prompt_tokens: number; completion_tokens: number; estimated_cost_usd: string | null };
type AiProps = {
    quota: AiQuota;
    usage_30d: AiUsage;
    can_generate: boolean;
    ready: boolean;
    history_limit: number;
    history: AiHistoryEntry[];
};
const props = defineProps<{
    project: { id: number; name: string; is_archived: boolean };
    repository: { full_name: string };
    pull_request: PullRequest;
    reviews: Review[];
    reviews_count: number;
    reviews_limit: number;
    files: ChangedFile[];
    files_count: number;
    files_limit: number;
    files_current: boolean;
    files_synced_at: string | null;
    can_sync_files: boolean;
    ai: AiProps;
}>();
const { locale, t } = useI18n();
const selectedLanguage = ref(['en', 'pl'].includes(locale.value) ? locale.value : 'other');
const customLanguage = ref(['en', 'pl'].includes(locale.value) ? '' : locale.value);
const includeCode = ref(false);
const approveMetadata = ref(false);
const approveCode = ref(false);
const generating = ref(false);
const aiError = ref('');
const selectedAnalysisId = ref<number | null>(props.ai.history[0]?.id ?? null);
const currentAnalysis = computed(() => props.ai.history.find(item => item.id === selectedAnalysisId.value) ?? props.ai.history[0] ?? null);
const languageToSend = computed(() => (selectedLanguage.value === 'other' ? customLanguage.value.trim() : selectedLanguage.value).toLowerCase());
const languageValid = computed(() => /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i.test(languageToSend.value) && languageToSend.value.length <= 35);
const hasActiveAnalysis = computed(() => props.ai.history.some(item => item.status === 'pending' || item.status === 'running'));
const canSubmitAi = computed(() => props.ai.can_generate && props.ai.ready && props.ai.quota.allowed && !generating.value && !hasActiveAnalysis.value && approveMetadata.value
    && (!includeCode.value || approveCode.value) && languageValid.value);
watch(() => props.ai.history[0]?.id, id => { if (id !== undefined) selectedAnalysisId.value = id; });
watch(includeCode, enabled => { if (!enabled) approveCode.value = false; });
function generateAiAnalysis(): void {
    if (!canSubmitAi.value) return;
    generating.value = true;
    aiError.value = '';
    router.post(`/projects/${props.project.id}/integrations/github/pulls/${props.pull_request.number}/ai-analyses`, {
        language: languageToSend.value,
        include_patches: includeCode.value,
        approve_external_sharing: approveMetadata.value,
        approve_code_sharing: includeCode.value && approveCode.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            approveMetadata.value = false;
            approveCode.value = false;
            includeCode.value = false;
        },
        onError: errors => {
            aiError.value = errors.analysis || errors.approve_external_sharing || errors.approve_code_sharing || errors.language
                || (t('screens.project.githubpullrequest.analysis_was_not_completed'));
        },
        onFinish: () => { generating.value = false; },
    });
}
function formatUsd(amount: string | null): string {
    if (amount === null) return '—';
    const number = Number(amount);
    return Number.isFinite(number) ? number.toFixed(8) : '—';
}
function analysisVersionLabel(entry: AiHistoryEntry): string {
    return `v${entry.version} · ${aiStatusLabel(entry.status)} · ${entry.language}`;
}
function aiStatusLabel(status: AiHistoryEntry['status']): string {
    const labels = {
        completed: t('screens.project.githubpullrequest.completed'),
        failed: t('screens.project.githubpullrequest.failed'),
        pending: t('screens.project.githubpullrequest.pending'),
        running: t('screens.project.githubpullrequest.running'),
    };
    return labels[status];
}
// Poll only while this PR has an active queued analysis. Inertia refreshes
// only the ai prop, preserving form state, scroll and the selected tab.
const syncingSnapshot = ref(false);
let aiPollTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    aiPollTimer = setInterval(() => {
        if (hasActiveAnalysis.value && !generating.value && !syncingSnapshot.value) {
            router.reload({ only: ['ai'] });
        }
    }, 5000);
});
onUnmounted(() => { if (aiPollTimer) clearInterval(aiPollTimer); });
const syncError = ref('');
function syncPullAndFiles(): void {
    if (!props.can_sync_files || syncingSnapshot.value) return;
    syncError.value = '';
    syncingSnapshot.value = true;
    router.post(`/projects/${props.project.id}/integrations/github/pulls/${props.pull_request.number}/sync`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            // Refresh only dependent Inertia props, without replacing the page or form state.
            router.reload({
                only: ['pull_request', 'files', 'files_count', 'files_current', 'files_synced_at', 'ai'],
            });
        },
        onError: errors => {
            syncError.value = errors.sync || (t('screens.project.githubpullrequest.could_not_refresh_the_pr_and_files'));
        },
        onFinish: () => { syncingSnapshot.value = false; },
    });
}
const isPl = computed(() => locale.value === 'pl');
const repositoryUrl = computed(() => `/projects/${props.project.id}/integrations/github?tab=pulls`);
const longDescription = computed(() => props.pull_request.body_html.length > 7000);
const dateFormatter = computed(() => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }));
function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : dateFormatter.value.format(date);
}
function statusLabel(state: PullRequest['state'], draft: boolean): string {
    if (state === 'merged') return t('screens.project.githubpullrequest.merged');
    if (state === 'closed') return t('screens.project.githubpullrequest.closed');
    if (draft) return t('screens.project.githubpullrequest.draft');
    return t('screens.project.githubpullrequest.open');
}
function reviewStatus(state: Review['state']): string {
    const statuses = {
        approved: t('screens.project.githubpullrequest.approved'),
        changes_requested: t('screens.project.githubpullrequest.changes_requested'),
        commented: t('screens.project.githubpullrequest.commented'),
        dismissed: t('screens.project.githubpullrequest.dismissed'),
    };
    return statuses[state];
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`PR #${pull_request.number} · ${project.name}`" />
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="space-y-3">
            <Link :href="repositoryUrl" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ t('screens.project.githubpullrequest.back_to_pull_requests') }}
            </Link>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <GitMerge class="size-4" /> {{ repository.full_name }} <span class="font-mono">#{{ pull_request.number }}</span>
                    </div>
                    <h1 class="break-words text-2xl font-semibold tracking-tight">{{ pull_request.title }}</h1>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :variant="pull_request.state === 'open' ? 'outline' : 'secondary'">
                            {{ statusLabel(pull_request.state, pull_request.is_draft) }}
                        </Badge>
                        <span class="text-xs text-muted-foreground">{{ project.name }}</span>
                    </div>
                </div>
                <Button as-child variant="outline">
                    <a :href="pull_request.web_url" target="_blank" rel="noopener noreferrer">
                        {{ t('screens.project.githubpullrequest.open_on_github') }} <ExternalLink class="size-4" />
                    </a>
                </Button>
            </div>
        </header>

        <Card class="gap-3">
            <CardHeader><CardTitle class="flex items-center gap-2 text-base"><GitBranch class="size-4" /> {{ t('screens.project.githubpullrequest.pull_request_details') }}</CardTitle></CardHeader>
            <CardContent class="space-y-5">
                <dl class="grid gap-5 text-sm sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.pr_author') }}</dt>
                        <dd class="font-medium">{{ pull_request.author.login || (t('screens.project.githubpullrequest.unknown_author')) }}</dd>
                        <dd v-if="pull_request.author.taskflow_user" class="text-xs text-muted-foreground">TaskFlow: {{ pull_request.author.taskflow_user.name }}</dd>
                    </div>
                    <div v-if="pull_request.state === 'merged'" class="space-y-1">
                        <dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.merged_by') }}</dt>
                        <dd class="font-medium">{{ pull_request.merged_by?.login || (t('screens.project.githubpullrequest.not_identified')) }}</dd>
                        <dd v-if="pull_request.merged_by?.taskflow_user" class="text-xs text-muted-foreground">TaskFlow: {{ pull_request.merged_by.taskflow_user.name }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.source_branch') }}</dt>
                        <dd class="break-all font-mono text-xs">{{ pull_request.source_branch || '—' }}</dd>
                        <dd v-if="pull_request.source_repository" class="break-all text-xs text-muted-foreground">{{ pull_request.source_repository }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.target_branch') }}</dt>
                        <dd class="break-all font-mono text-xs">{{ pull_request.target_branch || '—' }}</dd>
                    </div>
                    <div class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.created') }}</dt><dd>{{ formatDate(pull_request.remote_created_at) }}</dd></div>
                    <div class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.updated_on_github') }}</dt><dd>{{ formatDate(pull_request.remote_updated_at) }}</dd></div>
                    <div v-if="pull_request.merged_at" class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.merged_at') }}</dt><dd>{{ formatDate(pull_request.merged_at) }}</dd></div>
                    <div v-else-if="pull_request.closed_at" class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.githubpullrequest.closed_at') }}</dt><dd>{{ formatDate(pull_request.closed_at) }}</dd></div>
                </dl>
                <div v-if="pull_request.head_sha || pull_request.merge_commit_sha" class="border-t pt-4 text-xs text-muted-foreground">
                    <span v-if="pull_request.head_sha" class="mr-5">HEAD: <code class="font-mono">{{ pull_request.head_sha.slice(0, 12) }}</code></span>
                    <span v-if="pull_request.merge_commit_sha">{{ t('screens.project.githubpullrequest.merge_commit') }}: <code class="font-mono">{{ pull_request.merge_commit_sha.slice(0, 12) }}</code></span>
                </div>
                <div class="border-t pt-4">
                    <h2 class="mb-2 text-sm font-semibold">{{ t('screens.project.githubpullrequest.description') }}</h2>
                    <template v-if="pull_request.body_html">
                        <details v-if="longDescription" class="rounded-md border px-3 py-2">
                            <summary class="cursor-pointer select-none text-sm font-medium">{{ t('screens.project.githubpullrequest.show_full_pull_request_description') }}</summary>
                            <div class="task-markdown taskflow-pr-markdown mt-4 min-w-0 break-words text-sm" v-html="pull_request.body_html" />
                        </details>
                        <div v-else class="task-markdown taskflow-pr-markdown min-w-0 break-words text-sm" v-html="pull_request.body_html" />
                    </template>
                    <p v-else class="text-sm text-muted-foreground">{{ t('screens.project.githubpullrequest.no_description_stored_for_this_pull_request') }}</p>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-3">
            <CardHeader class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle class="flex items-center gap-2 text-base"><FileDiff class="size-4" /> {{ t('screens.project.githubpullrequest.changed_files') }}</CardTitle>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('screens.project.githubpullrequest.files_refer_to_the_current_head_revision_diff_previews') }}
                </p>
                <p v-if="syncError" role="alert" class="text-sm text-destructive">{{ syncError }}</p>
            </CardHeader>
            <CardContent class="space-y-3">
                <p v-if="!files_current" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    {{ t('screens.project.githubpullrequest.no_current_file_snapshot_use_sync_pr_and_files_in_the_a') }}
                </p>
                <template v-else>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-muted-foreground">{{ t('screens.project.githubpullrequest.changed_files_10f2a') }}: <strong class="text-foreground">{{ files_count }}</strong></span>
                        <time class="text-xs text-muted-foreground">{{ formatDate(files_synced_at) }}</time>
                    </div>
                    <p v-if="files_count === 0" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        {{ t('screens.project.githubpullrequest.github_returned_no_changed_files_for_this_pr_revision') }}
                    </p>
                    <div v-else class="divide-y rounded-lg border">
                        <details v-for="file in files" :key="file.path" class="group min-w-0 px-3 py-3">
                            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 text-sm marker:hidden">
                                <span class="min-w-0 break-all font-mono text-xs"><span class="mr-2 text-muted-foreground">{{ file.status }}</span>{{ file.path }}</span>
                                <span class="flex shrink-0 gap-2 font-mono text-xs"><span class="text-green-700 dark:text-green-400">+{{ file.additions }}</span><span class="text-red-700 dark:text-red-400">-{{ file.deletions }}</span></span>
                            </summary>
                            <div class="mt-3 space-y-2 border-t pt-3">
                                <p v-if="file.previous_path" class="break-all text-xs text-muted-foreground">{{ t('screens.project.githubpullrequest.previous_path') }}: {{ file.previous_path }}</p>
                                <pre v-if="file.patch" class="max-h-[34rem] overflow-auto rounded-md bg-muted p-3 font-mono text-xs leading-relaxed"><code>{{ file.patch }}</code></pre>
                                <p v-else class="text-xs text-muted-foreground">{{ t('screens.project.githubpullrequest.diff_preview_unavailable_binary_file_or_size_limit') }}</p>
                            </div>
                        </details>
                    </div>
                    <p v-if="files_count > files.length" class="text-xs text-muted-foreground">{{ isPl ? `Pokazano pierwsze ${files_limit} plików. Pozostałe są zapisane w bazie.` : `Showing the first ${files_limit} files. Others are stored in the database.` }}</p>
                </template>
            </CardContent>
        </Card>

        <Card class="gap-3" id="ai-analysis">
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base"><Sparkles class="size-4" /> {{ t('screens.project.githubpullrequest.ai_code_review') }}</CardTitle>
                <p class="text-sm text-muted-foreground">
                    {{ t('screens.project.githubpullrequest.ai_reviews_pr_metadata_and_optional_code_excerpts_analy') }}
                </p>
            </CardHeader>
            <CardContent class="space-y-5">
                <form v-if="ai.can_generate" class="space-y-4 rounded-lg border p-4" @submit.prevent="generateAiAnalysis">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">{{ t('screens.project.githubpullrequest.new_ai_code_review') }}</h3>
                        <p class="text-xs text-muted-foreground">
                            {{ t('screens.project.githubpullrequest.review_the_data_scope_selected_information_will_be_sent') }}
                        </p>
                    </div>
                    <div class="space-y-3 rounded-md border border-dashed p-3" role="status">
                        <p v-if="!ai.ready" class="text-sm text-muted-foreground">
                            {{ t('screens.project.githubpullrequest.refresh_this_pr_and_its_files_before_analyzing_without') }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('screens.project.githubpullrequest.pr_and_file_snapshots_are_current_you_can_queue_analysi') }}
                        </p>
                        <Button type="button" size="sm" variant="outline" :disabled="syncingSnapshot" @click="syncPullAndFiles">
                            <RefreshCw class="size-4" :class="{ 'animate-spin': syncingSnapshot }" />
                            {{ syncingSnapshot ? (t('screens.project.githubpullrequest.syncing')) : (t('screens.project.githubpullrequest.sync_pr_and_files')) }}
                        </Button>
                    </div>
                    <p v-if="syncError" role="alert" class="text-sm text-destructive">{{ syncError }}</p>
                    <div class="rounded-md border p-3 text-xs text-muted-foreground space-y-1" role="status">
                        <p class="font-medium text-foreground">{{ t('screens.project.githubpullrequest.project_analysis_quota') }}</p>
                        <p>{{ ai.quota.used_1h }} / {{ ai.quota.limit_1h || '∞' }} {{ t('screens.project.githubpullrequest.in_the_last_hour') }} ·
                            {{ ai.quota.used_24h }} / {{ ai.quota.limit_24h || '∞' }} {{ t('screens.project.githubpullrequest.in_the_last_24_hours') }}</p>
                        <p v-if="!ai.quota.allowed" class="text-destructive">{{ t('screens.project.githubpullrequest.quota_exhausted_a_new_analysis_cannot_be_queued_now') }}</p>
                    </div>
                    <div class="max-w-sm space-y-1.5">
                        <Label for="pr-ai-language">{{ t('screens.project.githubpullrequest.analysis_language') }}</Label>
                        <Select v-model="selectedLanguage">
                            <SelectTrigger id="pr-ai-language" class="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pl">Polski</SelectItem>
                                <SelectItem value="en">English</SelectItem>
                                <SelectItem value="de">Deutsch</SelectItem>
                                <SelectItem value="es">Español</SelectItem>
                                <SelectItem value="other">{{ t('screens.project.githubpullrequest.other_language') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input v-if="selectedLanguage === 'other'" v-model="customLanguage" maxlength="35" placeholder="np. fr, pt-br, uk" :aria-label="t('screens.project.githubpullrequest.language_code')" />
                    </div>
                    <div class="space-y-2 rounded-md bg-muted/40 p-3 text-sm">
                        <p class="font-medium">{{ t('screens.project.githubpullrequest.data_prepared_for_sharing') }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('screens.project.githubpullrequest.pr_metadata_changed_file_names_and_statistics_review_st') }}
                        </p>
                        <p class="text-xs font-medium">
                            {{ isPl ? `Dostępny snapshot: ${files_count} plików, ${reviews_count} przesłanych recenzji. HEAD: ${pull_request.head_sha?.slice(0, 12) || '—'}.` : `Available snapshot: ${files_count} files, ${reviews_count} submitted reviews. HEAD: ${pull_request.head_sha?.slice(0, 12) || '—'}.` }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('screens.project.githubpullrequest.pr_descriptions_raw_review_comments_taskflow_account_da') }}
                        </p>
                        <div class="flex items-start gap-2 pt-1">
                            <Checkbox id="pr-ai-include-code" :model-value="includeCode" @update:model-value="value => { includeCode = value === true; }" />
                            <Label for="pr-ai-include-code" class="cursor-pointer text-sm leading-snug font-normal">
                                {{ t('screens.project.githubpullrequest.also_include_screened_bounded_code_excerpts_optional') }}
                            </Label>
                        </div>
                        <p v-if="includeCode" class="text-xs text-muted-foreground">
                            {{ t('screens.project.githubpullrequest.sensitive_data_screening_cannot_guarantee_detection_of') }}
                        </p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start gap-2">
                            <Checkbox id="pr-ai-consent-meta" :model-value="approveMetadata" @update:model-value="value => { approveMetadata = value === true; }" />
                            <Label for="pr-ai-consent-meta" class="cursor-pointer text-sm leading-snug font-normal">
                                {{ t('screens.project.githubpullrequest.i_approve_a_one_time_transfer_of_the_listed_pr_metadata') }}
                            </Label>
                        </div>
                        <div v-if="includeCode" class="flex items-start gap-2">
                            <Checkbox id="pr-ai-consent-code" :model-value="approveCode" @update:model-value="value => { approveCode = value === true; }" />
                            <Label for="pr-ai-consent-code" class="cursor-pointer text-sm leading-snug font-normal">
                                {{ t('screens.project.githubpullrequest.i_separately_approve_sending_screened_code_excerpts_to') }}
                            </Label>
                        </div>
                    </div>
                    <p v-if="hasActiveAnalysis" role="status" class="text-sm text-muted-foreground">
                        {{ t('screens.project.githubpullrequest.analysis_queued_or_running_the_result_will_appear_here') }}
                    </p>
                    <p v-if="aiError" role="alert" class="text-sm text-destructive">{{ aiError }}</p>
                    <Button type="submit" :disabled="!canSubmitAi">
                        <Sparkles class="size-4" /> {{ generating ? (t('screens.project.githubpullrequest.queuing')) : (t('screens.project.githubpullrequest.start_ai_code_review')) }}
                    </Button>
                    <p class="text-xs text-muted-foreground">{{ t('screens.project.githubpullrequest.analysis_runs_in_a_background_queue_and_status_refreshe') }}</p>
                </form>
                <div v-else class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                    {{ t('screens.project.githubpullrequest.only_an_authorized_administrator_of_an_active_project_c') }}
                </div>
                <div class="rounded-lg border p-4 text-sm space-y-2">
                    <h3 class="font-semibold">{{ t('screens.project.githubpullrequest.ai_code_review_usage_last_30_days') }}</h3>
                    <p class="text-muted-foreground">{{ t('screens.project.githubpullrequest.completed_1e5c5') }}: {{ ai.usage_30d.completed }} · {{ t('screens.project.githubpullrequest.failed_6176f') }}: {{ ai.usage_30d.failed }}</p>
                    <p class="text-muted-foreground">{{ t('screens.project.githubpullrequest.input_output_tokens') }}: {{ ai.usage_30d.prompt_tokens.toLocaleString() }} / {{ ai.usage_30d.completion_tokens.toLocaleString() }}</p>
                    <p>{{ t('screens.project.githubpullrequest.estimated_cost') }}:
                        <strong v-if="ai.usage_30d.estimated_cost_usd !== null">{{ formatUsd(ai.usage_30d.estimated_cost_usd) }} USD</strong>
                        <span v-else class="text-muted-foreground">{{ t('screens.project.githubpullrequest.no_pricing_data') }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground">{{ isPl
                        ? `Szacunek tylko dla analiz z zapisaną ceną. Brak wyceny: ${ai.usage_30d.unknown_cost}. Nie obejmuje ewentualnych opłat za nieudane próby, cache i różnic w cenniku dostawcy. To nie faktura.`
                        : `Estimate covers only analyses with recorded pricing. Unpriced: ${ai.usage_30d.unknown_cost}. Excludes possible failed requests, cache and provider pricing differences. Not a billing statement.` }}</p>
                </div>
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ t('screens.project.githubpullrequest.ai_analysis_history') }}</h3>
                        <span class="text-xs text-muted-foreground">{{ ai.history.length }} / {{ ai.history_limit }}</span>
                    </div>
                    <p v-if="ai.history.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        {{ t('screens.project.githubpullrequest.no_analysis_has_been_generated_for_this_pr_yet') }}
                    </p>
                    <template v-else>
                        <div class="max-w-sm space-y-1.5">
                            <Label for="pr-ai-history-version">{{ t('screens.project.githubpullrequest.choose_version') }}</Label>
                            <Select :model-value="currentAnalysis ? String(currentAnalysis.id) : undefined" @update:model-value="value => { selectedAnalysisId = Number(value); }">
                                <SelectTrigger id="pr-ai-history-version" class="w-full"><SelectValue>{{ currentAnalysis ? analysisVersionLabel(currentAnalysis) : '' }}</SelectValue></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="entry in ai.history" :key="entry.id" :value="String(entry.id)">
                                        {{ analysisVersionLabel(entry) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <article v-if="currentAnalysis" class="space-y-4 rounded-lg border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">v{{ currentAnalysis.version }}</span>
                                    <Badge :variant="currentAnalysis.status === 'failed' ? 'destructive' : 'secondary'">{{ aiStatusLabel(currentAnalysis.status) }}</Badge>
                                    <Badge variant="outline">{{ currentAnalysis.language }}</Badge>
                                    <Badge v-if="currentAnalysis.include_patch_excerpts" variant="outline">{{ t('screens.project.githubpullrequest.with_code') }}</Badge>
                                </div>
                                <span class="text-xs text-muted-foreground">{{ formatDate(currentAnalysis.finished_at || currentAnalysis.created_at) }}</span>
                            </div>
                            <p class="break-all text-xs text-muted-foreground">HEAD: {{ currentAnalysis.source_head_sha.slice(0, 12) }} · {{ currentAnalysis.ai_model || currentAnalysis.ai_provider || '—' }}</p>
                            <p v-if="currentAnalysis.status === 'completed'" class="text-xs text-muted-foreground">
                                {{ t('screens.project.githubpullrequest.estimated_cost_a4b46') }}: {{ currentAnalysis.estimated_cost_usd !== null ? formatUsd(currentAnalysis.estimated_cost_usd) + ' USD' : (t('screens.project.githubpullrequest.no_pricing_data')) }}
                            </p>
                            <div v-if="currentAnalysis.status === 'completed' && currentAnalysis.result_html" class="task-markdown taskflow-pr-markdown min-w-0 break-words text-sm" v-html="currentAnalysis.result_html" />
                            <p v-else class="text-sm text-muted-foreground">
                                {{ currentAnalysis.status === 'failed' ? (t('screens.project.githubpullrequest.generation_failed_no_result_or_provider_error_details_w')) : (t('screens.project.githubpullrequest.no_result_available_yet')) }}
                            </p>
                            <p v-if="currentAnalysis.status === 'completed'" class="text-xs text-muted-foreground">
                                {{ t('screens.project.githubpullrequest.input_output_tokens') }}:
                                {{ currentAnalysis.prompt_tokens ?? '—' }} / {{ currentAnalysis.completion_tokens ?? '—' }}
                            </p>
                        </article>
                    </template>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-3">
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base"><History class="size-4" /> {{ t('screens.project.githubpullrequest.pull_request_reviews') }}</CardTitle>
                <p class="text-sm text-muted-foreground">
                    {{ t('screens.project.githubpullrequest.stored_submitted_pull_request_reviews_unsubmitted_draft') }}
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="text-muted-foreground">{{ t('screens.project.githubpullrequest.submitted_reviews') }}</span>
                    <span class="font-semibold tabular-nums">{{ reviews_count }}</span>
                </div>
                <div v-if="reviews.length" class="divide-y rounded-lg border">
                    <article v-for="review in reviews" :key="review.id" class="space-y-2 px-4 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium">{{ review.reviewer_login || (t('screens.project.githubpullrequest.unknown_reviewer')) }}</span>
                                <Badge :variant="review.state === 'changes_requested' ? 'destructive' : 'secondary'">{{ reviewStatus(review.state) }}</Badge>
                            </div>
                            <time class="text-xs text-muted-foreground">{{ formatDate(review.submitted_at) }}</time>
                        </div>
                        <p v-if="review.taskflow_reviewer" class="text-xs text-muted-foreground">TaskFlow: {{ review.taskflow_reviewer.name }}</p>
                        <p v-if="review.body" class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ review.body }}</p>
                        <p v-else class="text-xs text-muted-foreground">{{ t('screens.project.githubpullrequest.no_comment') }}</p>
                    </article>
                </div>
                <p v-else class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    {{ t('screens.project.githubpullrequest.no_stored_reviews_this_pr_may_not_have_reviews_yet_or_t') }}
                </p>
                <p v-if="reviews_count > reviews.length" class="text-xs text-muted-foreground">
                    {{ isPl ? `Pokazano ${reviews_limit} najnowszych recenzji. Pozostałe są zapisane w bazie.` : `Showing the latest ${reviews_limit} reviews. Others are stored in the database.` }}
                </p>
                <p class="text-xs text-muted-foreground">{{ t('screens.project.githubpullrequest.reviews_last_checked') }}: {{ formatDate(pull_request.reviews_checked_at) }}</p>
            </CardContent>
        </Card>
        <p class="text-sm text-muted-foreground">{{ t('screens.project.githubpullrequest.details_use_stored_taskflow_data_refresh_prs_and_review') }}</p>
    </div>
</template>

<style scoped>
.taskflow-pr-markdown :deep(details) { margin: 0.65rem 0; padding: 0.6rem 0.8rem; border: 1px solid var(--border); border-radius: 0.5rem; }
.taskflow-pr-markdown :deep(summary) { cursor: pointer; font-weight: 600; }
.taskflow-pr-markdown :deep(details > :not(summary)) { margin-top: 0.7rem; }
/* The renderer strips all untrusted `open` attributes, so Dependabot sections
   start collapsed. Once expanded, keep very long release notes manageable. */
.taskflow-pr-markdown :deep(details[open]) {
    max-height: min(34rem, 65vh);
    overflow-y: auto;
    overscroll-behavior: contain;
}
.taskflow-pr-markdown :deep(details[open] > summary) {
    position: sticky;
    top: 0;
    z-index: 1;
    padding-block: 0.25rem;
    background: var(--card);
}
.taskflow-pr-markdown :deep(pre) { max-width: 100%; overflow-x: auto; }
</style>
