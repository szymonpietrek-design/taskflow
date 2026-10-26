<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, ExternalLink, GitBranch, GitMerge, History, LockKeyhole, RefreshCw, ShieldCheck, Trash2 } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Repository = {
    full_name: string;
    installation_id: number;
    account_login: string;
    default_branch: string;
    start_sha: string;
    last_synced_sha: string;
    connected_at: string;
    last_synced_at: string | null;
};
type GitHubCommit = { sha: string; subject: string; author: string | null; taskflow_author: { id: number; name: string } | null; committed_at: string | null };
type PullRequest = {
    number: number;
    title: string;
    web_url: string;
    state: 'open' | 'closed' | 'merged';
    is_draft: boolean;
    author_login: string | null;
    taskflow_author: { id: number; name: string } | null;
    source_branch: string | null;
    target_branch: string | null;
    reviews_count: number;
    remote_updated_at: string | null;
};
type SyncRun = {
    id: number;
    status: 'completed' | 'running' | 'failed';
    fetched_count: number;
    imported_count: number;
    error_message: string | null;
    started_at: string | null;
};
const props = defineProps<{
    project: { id: number; name: string; is_archived: boolean };
    configured: boolean;
    can_connect: boolean;
    repository: Repository | null;
    installations: { installation_id: number; account_login: string }[];
    commits: GitHubCommit[];
    commits_count: number;
    pull_requests: PullRequest[];
    pull_requests_count: number;
    initial_tab: 'commits' | 'pulls';
    legacy_authors_pending: number;
    sync_runs: SyncRun[];
}>();
const { locale, t, formatText } = useI18n();
const base = computed(() => `/projects/${props.project.id}/integrations/github`);
const form = useForm({ installation_id: props.installations.length === 1 ? String(props.installations[0].installation_id) : '', repository: '' });
const activeHistory = ref<'commits' | 'pulls'>(props.initial_tab);
const disconnecting = ref(false);
const confirmDisconnect = ref(false);
const syncing = ref(false);
const checkingAuthors = ref(false);
const authorsError = ref('');
const syncError = ref('');
const activitySync = ref<'pulls' | 'reviews' | null>(null);
const activityError = ref('');
function syncActivity(kind: 'pulls' | 'reviews'): void {
    if (!props.repository || !props.configured || !props.can_connect || props.project.is_archived || activitySync.value || syncing.value || checkingAuthors.value) return;
    activitySync.value = kind;
    activityError.value = '';
    router.post(`${base.value}/${kind}/sync`, {}, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            activityError.value = errors[kind] || (t('screens.project.github.could_not_synchronize_data'));
        },
        onFinish: () => { activitySync.value = null; },
    });
}

const dateFormatter = computed(() => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }));
function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : dateFormatter.value.format(date);
}
function sync(): void {
    if (!props.repository || !props.can_connect || props.project.is_archived || syncing.value) return;
    syncError.value = '';
    syncing.value = true;
    router.post(`${base.value}/sync`, {}, {
        preserveScroll: true,
        onError: (errors) => { syncError.value = errors.sync || (t('screens.project.github.synchronization_failed')); },
        onFinish: () => { syncing.value = false; },
    });
}

function backfillAuthors(): void {
    if (!props.repository || !props.can_connect || props.project.is_archived || checkingAuthors.value) return;
    authorsError.value = '';
    checkingAuthors.value = true;
    router.post(`${base.value}/authors`, {}, {
        preserveScroll: true,
        onError: (errors) => { authorsError.value = errors.authors || (t('screens.project.github.author_lookup_failed')); },
        onFinish: () => { checkingAuthors.value = false; },
    });
}

function connect(): void {
    if (!props.can_connect || !props.configured || props.repository || form.processing) return;
    form.post(base.value, { preserveScroll: true, onSuccess: () => form.reset() });
}
function disconnect(): void {
    if (!props.can_connect || !props.repository || disconnecting.value) return;
    disconnecting.value = true;
    router.delete(base.value, {
        preserveScroll: true,
        onSuccess: () => { confirmDisconnect.value = false; },
        onFinish: () => { disconnecting.value = false; },
    });
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`GitHub · ${project.name}`" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-2">
            <Link :href="`/projects/${project.id}/integrations/repository`" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ t('screens.project.github.back_to_git_integrations') }}
            </Link>
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight"><GitBranch class="size-6" /> GitHub</h1>
            <p class="text-sm text-muted-foreground">
                {{ t('screens.project.github.repository_linked_to_this_project') }} · {{ project.name }}
            </p>
        </div>
        <div v-if="project.is_archived" class="rounded-lg border p-3 text-sm text-muted-foreground">
            {{ t('screens.project.github.the_project_is_archived_this_integration_is_read_only') }}
        </div>
        <Card class="gap-3">
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <ShieldCheck class="size-4" /> {{ t('screens.project.github.repository_connection') }}
                </CardTitle>
                <p class="text-sm text-muted-foreground">
                    {{ t('screens.project.github.the_first_connection_stores_the_current_commit_as_a_bas') }}
                </p>
            </CardHeader>
            <CardContent class="space-y-5">
                <template v-if="repository">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{{ t('screens.project.github.connected') }}</Badge>
                        <a :href="`https://github.com/${repository.full_name}`" target="_blank" rel="noopener noreferrer" class="font-medium underline underline-offset-4">
                            {{ repository.full_name }}
                        </a>
                    </div>
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.github.github_app_installation') }}</dt><dd class="font-medium">{{ repository.account_login }} · #{{ repository.installation_id }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.github.branch') }}</dt><dd class="font-medium">{{ repository.default_branch }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.github.starting_sha') }}</dt><dd class="font-mono text-xs break-all">{{ repository.start_sha }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.github.last_synced_sha') }}</dt><dd class="font-mono text-xs break-all">{{ repository.last_synced_sha }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ t('screens.project.github.last_synchronization') }}</dt><dd>{{ repository.last_synced_at ? formatDate(repository.last_synced_at) : (t('screens.project.github.not_started_yet')) }}</dd></div>
                    </dl>
                    <div v-if="can_connect" class="border-t pt-4">
                        <Button v-if="!confirmDisconnect" type="button" variant="outline" @click="confirmDisconnect = true">
                            <Trash2 class="size-4" /> {{ t('screens.project.github.disconnect_repository') }}
                        </Button>
                        <div v-else class="flex flex-wrap items-center gap-3">
                            <p class="text-sm text-muted-foreground">{{ t('screens.project.github.confirm_disconnecting_this_repository') }}</p>
                            <Button type="button" variant="destructive" :disabled="disconnecting" @click="disconnect">{{ t('screens.project.github.disconnect') }}</Button>
                            <Button type="button" variant="outline" @click="confirmDisconnect = false">{{ t('screens.project.github.cancel') }}</Button>
                        </div>
                    </div>
                </template>
                <div v-else-if="!can_connect" class="flex items-start gap-2 text-sm text-muted-foreground">
                    <LockKeyhole class="mt-0.5 size-4 shrink-0" />
                    <p>{{ t('screens.project.github.only_workspace_owners_and_administrators_can_connect_a') }}</p>
                </div>
                <div v-else-if="!configured" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    {{ t('screens.project.github.github_app_is_not_configured_a_system_administrator_can') }}
                </div>
                <form v-else class="max-w-xl space-y-3" @submit.prevent="connect">
                    <label for="github-installation" class="block text-sm font-medium">Installation ID</label>
                    <Input id="github-installation" v-model="form.installation_id" required inputmode="numeric" pattern="[0-9]+" maxlength="20" placeholder="12345678" autocomplete="off" :aria-invalid="!!form.errors.installation_id" />
                    <p v-if="form.errors.installation_id" class="text-sm text-destructive">{{ form.errors.installation_id }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('screens.project.github.number_from_github_com_settings_installations_stored_in') }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('screens.project.github.only_a_system_administrator_can_approve_a_new_installat') }}</p>
                    <div v-if="installations.length" class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>{{ t('screens.project.github.installations_in_this_workspace') }}</span>
                        <Button v-for="item in installations" :key="item.installation_id" type="button" size="sm" variant="outline" @click="form.installation_id = String(item.installation_id)">
                            {{ item.account_login }} · #{{ item.installation_id }}
                        </Button>
                    </div>
                    <label for="github-repository" class="block text-sm font-medium">{{ t('screens.project.github.github_repository') }}</label>
                    <Input id="github-repository" v-model="form.repository" required maxlength="200" placeholder="owner/repository" autocomplete="off" :aria-invalid="!!form.errors.repository" />
                    <p v-if="form.errors.repository" class="text-sm text-destructive">{{ form.errors.repository }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('screens.project.github.the_repository_must_be_accessible_to_the_installed_gith') }}</p>
                    <Button type="submit" :disabled="form.processing || !form.repository.trim() || !form.installation_id.trim()"><GitBranch class="size-4" /> {{ t('screens.project.github.connect_repository') }}</Button>
                </form>
            </CardContent>
        </Card>
        <template v-if="repository">
            <div class="flex flex-wrap gap-2" role="group" :aria-label="t('screens.project.github.repository_activity_view')">
                <Button type="button" :variant="activeHistory === 'commits' ? 'secondary' : 'outline'"
                    :aria-pressed="activeHistory === 'commits'" @click="activeHistory = 'commits'">
                    <History class="size-4" /> {{ t('screens.project.github.commits') }}
                    <span class="text-xs tabular-nums opacity-70">{{ commits_count }}</span>
                </Button>
                <Button type="button" :variant="activeHistory === 'pulls' ? 'secondary' : 'outline'"
                    :aria-pressed="activeHistory === 'pulls'" @click="activeHistory = 'pulls'">
                    <GitMerge class="size-4" /> {{ t('github.pullRequests') }}
                    <span class="text-xs tabular-nums opacity-70">{{ pull_requests_count }}</span>
                </Button>
            </div>
            <Card v-if="activeHistory === 'commits'" class="gap-3">
                <CardHeader class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base"><History class="size-4" /> {{ t('screens.project.github.github_commits') }}</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">{{ t('screens.project.github.only_changes_after_the_saved_baseline_no_ai_analysis') }}</p>
                        </div>
                        <Button v-if="can_connect && !project.is_archived" type="button" :disabled="syncing || !!activitySync || !configured" @click="sync">
                            <RefreshCw class="size-4" :class="syncing ? 'animate-spin' : ''" />
                            {{ syncing ? (t('screens.project.github.synchronizing')) : (t('screens.project.github.sync_now')) }}
                        </Button>
                    </div>
                    <p v-if="syncError" role="alert" class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{{ syncError }}</p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="text-muted-foreground">{{ t('screens.project.github.stored_commits') }}</span>
                        <span class="font-semibold tabular-nums">{{ commits_count }}</span>
                    </div>
                    <div v-if="legacy_authors_pending > 0 && can_connect && !project.is_archived" class="rounded-lg border border-dashed p-3 text-sm">
                        <p class="text-muted-foreground">{{ formatText('github.legacyAuthorsPending', { count: legacy_authors_pending }) }}</p>
                        <Button class="mt-2" variant="outline" size="sm" type="button" :disabled="checkingAuthors || !!activitySync || !configured" @click="backfillAuthors">
                            <RefreshCw class="size-4" :class="checkingAuthors ? 'animate-spin' : ''" />
                            {{ checkingAuthors ? (t('screens.project.github.checking')) : (t('screens.project.github.check_existing_authors')) }}
                        </Button>
                        <p v-if="authorsError" role="alert" class="mt-2 text-sm text-destructive">{{ authorsError }}</p>
                    </div>
                    <div v-if="commits.length" class="divide-y rounded-lg border">
                        <a v-for="commit in commits" :key="commit.sha" :href="`https://github.com/${repository.full_name}/commit/${commit.sha}`"
                           target="_blank" rel="noopener noreferrer" class="flex items-start justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/40">
                            <div class="min-w-0 space-y-1">
                                <p class="break-words text-sm font-medium">{{ commit.subject || commit.sha.slice(0, 7) }}</p>
                                <p class="text-xs text-muted-foreground">{{ commit.author || 'GitHub' }} · {{ formatDate(commit.committed_at) }}</p>
                                <p v-if="commit.taskflow_author" class="text-xs text-foreground">
                                    {{ t('screens.project.github.taskflow_user') }} <span class="font-medium">{{ commit.taskflow_author.name }}</span>
                                </p>
                                <p v-else class="text-xs text-muted-foreground">{{ t('screens.project.github.not_linked_to_a_taskflow_user') }}</p>
                            </div>
                            <span class="shrink-0 font-mono text-xs text-muted-foreground">{{ commit.sha.slice(0, 7) }}</span>
                        </a>
                    </div>
                    <p v-else class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        {{ t('screens.project.github.no_new_commits_commits_predating_the_connection_are_not') }}
                    </p>
                    <p v-if="commits_count > commits.length" class="text-xs text-muted-foreground">{{ t('screens.project.github.showing_the_latest_15_commits_the_rest_are_stored_in_th') }}</p>
                </CardContent>
            </Card>
            <Card v-else class="gap-3">
                <CardHeader class="space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="flex items-center gap-2 text-base"><GitMerge class="size-4" /> {{ t('github.pullRequests') }}</CardTitle>
                            <p class="text-sm text-muted-foreground">{{ t('screens.project.github.pull_requests_and_reviews_stored_in_taskflow') }}</p>
                        </div>
                        <div v-if="can_connect && !project.is_archived" class="flex flex-wrap gap-2">
                            <Button type="button" size="sm" :disabled="!!activitySync || syncing || checkingAuthors || !configured" @click="syncActivity('pulls')">
                                <RefreshCw class="size-4" :class="activitySync === 'pulls' ? 'animate-spin' : ''" />
                                {{ activitySync === 'pulls' ? (t('screens.project.github.syncing_prs')) : (t('screens.project.github.sync_pull_requests')) }}
                            </Button>
                            <Button type="button" size="sm" variant="outline" :disabled="!!activitySync || syncing || checkingAuthors || !configured || pull_requests_count === 0" @click="syncActivity('reviews')">
                                <RefreshCw class="size-4" :class="activitySync === 'reviews' ? 'animate-spin' : ''" />
                                {{ activitySync === 'reviews' ? (t('screens.project.github.refreshing_reviews')) : (t('screens.project.github.refresh_reviews')) }}
                            </Button>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ t('screens.project.github.sync_prs_first_then_refresh_reviews_up_to_10_prs_per_re') }}</p>
                    <p v-if="activityError" role="alert" class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{{ activityError }}</p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="text-muted-foreground">{{ t('screens.project.github.stored_pull_requests') }}</span>
                        <span class="font-semibold tabular-nums">{{ pull_requests_count }}</span>
                    </div>
                    <div v-if="pull_requests.length" class="divide-y rounded-lg border">
                        <div v-for="pull in pull_requests" :key="pull.number"
                            class="space-y-2 px-4 py-3 transition-colors hover:bg-muted/40">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs text-muted-foreground">#{{ pull.number }}</span>
                                    <Badge :variant="pull.state === 'open' ? 'outline' : 'secondary'">
                                        {{ pull.is_draft && pull.state === 'open' ? (t('screens.project.github.draft')) :
                                            pull.state === 'merged' ? (t('screens.project.github.merged')) :
                                            pull.state === 'closed' ? (t('screens.project.github.closed')) :
                                            (t('screens.project.github.open')) }}
                                    </Badge>
                                </div>
                                <a :href="pull.web_url" target="_blank" rel="noopener noreferrer"
                                    class="rounded p-1 text-muted-foreground hover:text-foreground"
                                    :aria-label="t('screens.project.github.open_pull_request_on_github')">
                                    <ExternalLink class="size-4" />
                                </a>
                            </div>
                            <Link :href="`${base}/pulls/${pull.number}`" class="block break-words text-sm font-medium hover:underline">
                                {{ pull.title }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ pull.author_login || (t('screens.project.github.unknown_author')) }}
                                · {{ t('screens.project.github.updated') }}: {{ formatDate(pull.remote_updated_at) }}
                            </p>
                            <p v-if="pull.taskflow_author" class="text-xs text-foreground">
                                {{ t('screens.project.github.taskflow_user') }}
                                <span class="font-medium">{{ pull.taskflow_author.name }}</span>
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                {{ t('screens.project.github.author_not_linked_to_a_taskflow_user') }}
                            </p>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                <span v-if="pull.source_branch && pull.target_branch" class="break-all font-mono">
                                    {{ pull.source_branch }} → {{ pull.target_branch }}
                                </span>
                                <span>{{ t('screens.project.github.reviews') }} {{ pull.reviews_count }}</span>
                            </div>
                            <Link :href="`${base}/pulls/${pull.number}`" class="inline-flex items-center text-xs font-medium underline underline-offset-4 hover:text-foreground">
                                {{ t('screens.project.github.view_details_and_reviews') }}
                            </Link>
                        </div>
                    </div>
                    <p v-else class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        {{ t('screens.project.github.no_pull_requests_imported_yet_click_sync_pull_requests') }}
                    </p>
                    <p v-if="pull_requests_count > pull_requests.length" class="text-xs text-muted-foreground">
                        {{ t('screens.project.github.showing_the_15_most_recently_updated_pull_requests_the') }}
                    </p>
                </CardContent>
            </Card>
            <Card v-if="sync_runs.length && activeHistory === 'commits'" class="gap-3">
                <CardHeader><CardTitle class="text-base">{{ t('screens.project.github.synchronization_history') }}</CardTitle></CardHeader>
                <CardContent class="divide-y">
                    <div v-for="run in sync_runs" :key="run.id" class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-medium">{{ run.status === 'completed' ? (t('screens.project.github.completed')) : run.status === 'failed' ? (t('screens.project.github.failed')) : (t('screens.project.github.running')) }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatDate(run.started_at) }} · {{ run.imported_count }} {{ t('screens.project.github.new') }} / {{ run.fetched_count }} {{ t('screens.project.github.fetched') }}</p>
                            <p v-if="run.error_message" class="text-xs text-destructive">{{ run.error_message }}</p>
                        </div>
                        <Badge :variant="run.status === 'failed' ? 'destructive' : 'secondary'">{{ run.status === 'completed' ? 'OK' : run.status === 'failed' ? (t('screens.project.github.error')) : '…' }}</Badge>
                    </div>
                </CardContent>
            </Card>
        </template>
        <p class="text-sm text-muted-foreground">
            {{ t('screens.project.github.review_history_is_available_in_pr_details_file_change_a') }}
        </p>
    </div>
</template>
