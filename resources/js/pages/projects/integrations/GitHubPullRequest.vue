<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, ExternalLink, GitBranch, GitMerge, History } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import '@/components/tasks/task-markdown-typography.css';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
type Review = {
    id: number;
    state: 'approved' | 'changes_requested' | 'commented' | 'dismissed';
    reviewer_login: string | null;
    taskflow_reviewer: LinkedUser;
    body: string | null;
    submitted_at: string | null;
};
const props = defineProps<{
    project: { id: number; name: string; is_archived: boolean };
    repository: { full_name: string };
    pull_request: PullRequest;
    reviews: Review[];
    reviews_count: number;
    reviews_limit: number;
}>();
const { locale } = useI18n();
const isPl = computed(() => locale.value === 'pl');
const repositoryUrl = computed(() => `/projects/${props.project.id}/integrations/github?tab=pulls`);
const longDescription = computed(() => props.pull_request.body_html.length > 7000);
const dateFormatter = computed(() => new Intl.DateTimeFormat(isPl.value ? 'pl-PL' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }));
function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : dateFormatter.value.format(date);
}
function statusLabel(state: PullRequest['state'], draft: boolean): string {
    if (state === 'merged') return isPl.value ? 'Scalony' : 'Merged';
    if (state === 'closed') return isPl.value ? 'Zamknięty' : 'Closed';
    if (draft) return isPl.value ? 'Szkic' : 'Draft';
    return isPl.value ? 'Otwarty' : 'Open';
}
function reviewStatus(state: Review['state']): string {
    const statuses = {
        approved: isPl.value ? 'Zatwierdzono' : 'Approved',
        changes_requested: isPl.value ? 'Wymagane zmiany' : 'Changes requested',
        commented: isPl.value ? 'Komentarz' : 'Commented',
        dismissed: isPl.value ? 'Odrzucono recenzję' : 'Dismissed',
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
                <ArrowLeft class="size-4" /> {{ isPl ? 'Wróć do Pull Requestów' : 'Back to pull requests' }}
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
                        {{ isPl ? 'Otwórz na GitHubie' : 'Open on GitHub' }} <ExternalLink class="size-4" />
                    </a>
                </Button>
            </div>
        </header>

        <Card class="gap-3">
            <CardHeader><CardTitle class="flex items-center gap-2 text-base"><GitBranch class="size-4" /> {{ isPl ? 'Szczegóły zgłoszenia' : 'Pull request details' }}</CardTitle></CardHeader>
            <CardContent class="space-y-5">
                <dl class="grid gap-5 text-sm sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ isPl ? 'Autor PR' : 'PR author' }}</dt>
                        <dd class="font-medium">{{ pull_request.author.login || (isPl ? 'Nieznany autor' : 'Unknown author') }}</dd>
                        <dd v-if="pull_request.author.taskflow_user" class="text-xs text-muted-foreground">TaskFlow: {{ pull_request.author.taskflow_user.name }}</dd>
                    </div>
                    <div v-if="pull_request.state === 'merged'" class="space-y-1">
                        <dt class="text-muted-foreground">{{ isPl ? 'Scalono przez' : 'Merged by' }}</dt>
                        <dd class="font-medium">{{ pull_request.merged_by?.login || (isPl ? 'Nieustalono' : 'Not identified') }}</dd>
                        <dd v-if="pull_request.merged_by?.taskflow_user" class="text-xs text-muted-foreground">TaskFlow: {{ pull_request.merged_by.taskflow_user.name }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ isPl ? 'Gałąź źródłowa' : 'Source branch' }}</dt>
                        <dd class="break-all font-mono text-xs">{{ pull_request.source_branch || '—' }}</dd>
                        <dd v-if="pull_request.source_repository" class="break-all text-xs text-muted-foreground">{{ pull_request.source_repository }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ isPl ? 'Gałąź docelowa' : 'Target branch' }}</dt>
                        <dd class="break-all font-mono text-xs">{{ pull_request.target_branch || '—' }}</dd>
                    </div>
                    <div class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Utworzono' : 'Created' }}</dt><dd>{{ formatDate(pull_request.remote_created_at) }}</dd></div>
                    <div class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Zaktualizowano na GitHubie' : 'Updated on GitHub' }}</dt><dd>{{ formatDate(pull_request.remote_updated_at) }}</dd></div>
                    <div v-if="pull_request.merged_at" class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Scalono' : 'Merged at' }}</dt><dd>{{ formatDate(pull_request.merged_at) }}</dd></div>
                    <div v-else-if="pull_request.closed_at" class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Zamknięto' : 'Closed at' }}</dt><dd>{{ formatDate(pull_request.closed_at) }}</dd></div>
                </dl>
                <div v-if="pull_request.head_sha || pull_request.merge_commit_sha" class="border-t pt-4 text-xs text-muted-foreground">
                    <span v-if="pull_request.head_sha" class="mr-5">HEAD: <code class="font-mono">{{ pull_request.head_sha.slice(0, 12) }}</code></span>
                    <span v-if="pull_request.merge_commit_sha">{{ isPl ? 'Commit scalenia' : 'Merge commit' }}: <code class="font-mono">{{ pull_request.merge_commit_sha.slice(0, 12) }}</code></span>
                </div>
                <div class="border-t pt-4">
                    <h2 class="mb-2 text-sm font-semibold">{{ isPl ? 'Opis' : 'Description' }}</h2>
                    <template v-if="pull_request.body_html">
                        <details v-if="longDescription" class="rounded-md border px-3 py-2">
                            <summary class="cursor-pointer select-none text-sm font-medium">{{ isPl ? 'Pokaż pełny opis Pull Requesta' : 'Show full pull request description' }}</summary>
                            <div class="task-markdown taskflow-pr-markdown mt-4 min-w-0 break-words text-sm" v-html="pull_request.body_html" />
                        </details>
                        <div v-else class="task-markdown taskflow-pr-markdown min-w-0 break-words text-sm" v-html="pull_request.body_html" />
                    </template>
                    <p v-else class="text-sm text-muted-foreground">{{ isPl ? 'Brak opisu w zapisanym Pull Requeście.' : 'No description stored for this pull request.' }}</p>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-3">
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base"><History class="size-4" /> {{ isPl ? 'Historia Code Review' : 'Code review history' }}</CardTitle>
                <p class="text-sm text-muted-foreground">
                    {{ isPl ? 'Zapisane i przesłane recenzje Pull Requesta. Nieopublikowane szkice nie są wyświetlane.' : 'Stored, submitted pull request reviews. Unsubmitted drafts are not shown.' }}
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="text-muted-foreground">{{ isPl ? 'Przesłane recenzje' : 'Submitted reviews' }}</span>
                    <span class="font-semibold tabular-nums">{{ reviews_count }}</span>
                </div>
                <div v-if="reviews.length" class="divide-y rounded-lg border">
                    <article v-for="review in reviews" :key="review.id" class="space-y-2 px-4 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium">{{ review.reviewer_login || (isPl ? 'Nieznany recenzent' : 'Unknown reviewer') }}</span>
                                <Badge :variant="review.state === 'changes_requested' ? 'destructive' : 'secondary'">{{ reviewStatus(review.state) }}</Badge>
                            </div>
                            <time class="text-xs text-muted-foreground">{{ formatDate(review.submitted_at) }}</time>
                        </div>
                        <p v-if="review.taskflow_reviewer" class="text-xs text-muted-foreground">TaskFlow: {{ review.taskflow_reviewer.name }}</p>
                        <p v-if="review.body" class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ review.body }}</p>
                        <p v-else class="text-xs text-muted-foreground">{{ isPl ? 'Bez komentarza.' : 'No comment.' }}</p>
                    </article>
                </div>
                <p v-else class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    {{ isPl ? 'Brak zapisanych recenzji. Możliwe, że PR nie został jeszcze zrecenzowany lub recenzje nie zostały zsynchronizowane.' : 'No stored reviews. This PR may not have reviews yet, or they may not have been synchronized.' }}
                </p>
                <p v-if="reviews_count > reviews.length" class="text-xs text-muted-foreground">
                    {{ isPl ? `Pokazano ${reviews_limit} najnowszych recenzji. Pozostałe są zapisane w bazie.` : `Showing the latest ${reviews_limit} reviews. Others are stored in the database.` }}
                </p>
                <p class="text-xs text-muted-foreground">{{ isPl ? 'Ostatnie sprawdzenie recenzji' : 'Reviews last checked' }}: {{ formatDate(pull_request.reviews_checked_at) }}</p>
            </CardContent>
        </Card>
        <p class="text-sm text-muted-foreground">{{ isPl ? 'Szczegóły pochodzą z zapisanych danych TaskFlow. Aktualizuj PR-y i recenzje na ekranie integracji Git.' : 'Details use stored TaskFlow data. Refresh PRs and reviews from the Git integration page.' }}</p>
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
