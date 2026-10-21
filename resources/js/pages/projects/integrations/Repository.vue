<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, ArrowRight, GitBranch, GitFork, GitMerge, LockKeyhole, Server, ShieldCheck } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ProviderKey = 'github' | 'gitlab' | 'gitea';
type Provider = { key: ProviderKey; name: string; available: boolean; configured: boolean };
type Repository = {
    provider: string | null;
    full_name: string;
    default_branch: string;
    last_synced_at: string | null;
    commits_count: number;
};
const props = defineProps<{
    project: { id: number; name: string; is_archived: boolean };
    can_manage: boolean;
    repository: Repository | null;
    providers: Provider[];
}>();
const { locale } = useI18n();
const isPl = computed(() => locale.value === 'pl');
const gitHubUrl = computed(() => `/projects/${props.project.id}/integrations/github`);
const currentProvider = computed(() => props.providers.find((item) => item.key === props.repository?.provider));
const formatter = computed(() => new Intl.DateTimeFormat(isPl.value ? 'pl-PL' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }));
function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : formatter.value.format(date);
}
function providerHint(provider: Provider): string {
    if (!provider.available) return isPl.value ? 'Obsługa zostanie dodana w kolejnym etapie.' : 'Support will be added in a future stage.';
    if (!provider.configured) return isPl.value ? 'Wymagana konfiguracja GitHub App przez administratora systemu.' : 'A system administrator must configure GitHub App.';
    return isPl.value ? 'Repozytoria przez GitHub App.' : 'Repositories connected through GitHub App.';
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`${isPl ? 'Integracje Git' : 'Git integrations'} · ${project.name}`" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-2">
            <Link :href="`/projects/${project.id}`" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ isPl ? 'Wróć do projektu' : 'Back to project' }}
            </Link>
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                <GitBranch class="size-6" /> {{ isPl ? 'Integracje Git' : 'Git integrations' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ isPl ? 'Połącz repozytorium z projektem' : 'Connect a repository to your project' }} · {{ project.name }}
            </p>
        </div>

        <div v-if="project.is_archived" class="flex items-start gap-2 rounded-lg border p-3 text-sm text-muted-foreground">
            <LockKeyhole class="mt-0.5 size-4 shrink-0" />
            {{ isPl ? 'Projekt zarchiwizowany — integracja jest dostępna tylko do odczytu.' : 'Archived project — the integration is read-only.' }}
        </div>

        <Card v-if="repository" class="gap-3">
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <ShieldCheck class="size-4" /> {{ isPl ? 'Połączone repozytorium' : 'Connected repository' }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{{ currentProvider?.name ?? repository.provider ?? 'VCS' }}</Badge>
                    <span class="break-all font-medium">{{ repository.full_name }}</span>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ isPl ? 'Gałąź' : 'Branch' }}</dt>
                        <dd class="font-medium">{{ repository.default_branch }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ isPl ? 'Zapisane commity' : 'Stored commits' }}</dt>
                        <dd class="font-medium tabular-nums">{{ repository.commits_count }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ isPl ? 'Ostatnia synchronizacja' : 'Last synchronization' }}</dt>
                        <dd>{{ formatDate(repository.last_synced_at) }}</dd>
                    </div>
                </dl>
                <Button v-if="repository.provider === 'github'" variant="outline" as-child>
                    <Link :href="gitHubUrl">
                        {{ isPl ? 'Zarządzaj repozytorium GitHub' : 'Manage GitHub repository' }}
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
                <p v-else class="text-sm text-muted-foreground">
                    {{ isPl ? 'Ten dostawca nie ma jeszcze interfejsu zarządzania.' : 'This provider does not have a management interface yet.' }}
                </p>
            </CardContent>
        </Card>

        <section v-if="!repository" class="space-y-3" aria-labelledby="vcs-providers-title">
            <div class="space-y-1">
                <h2 id="vcs-providers-title" class="text-base font-semibold">
                    {{ isPl ? 'Dostawcy repozytoriów' : 'Repository providers' }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ isPl ? 'Wybierz dostawcę dla tego projektu. Inne projekty mogą korzystać z różnych usług Git.' : 'Choose a provider for this project. Other projects may use different Git services.' }}
                </p>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                <Card v-for="provider in providers" :key="provider.key" class="gap-3" :class="!provider.available ? 'bg-muted/20' : ''">
                    <CardHeader class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex size-10 items-center justify-center rounded-lg border bg-background">
                                <GitBranch v-if="provider.key === 'github'" class="size-5" />
                                <GitMerge v-else-if="provider.key === 'gitlab'" class="size-5" />
                                <GitFork v-else class="size-5" />
                            </span>
                            <Badge variant="secondary">{{ provider.available ? (isPl ? 'Dostępny' : 'Available') : (isPl ? 'W przygotowaniu' : 'Coming soon') }}</Badge>
                        </div>
                        <CardTitle class="text-base">{{ provider.name }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col items-start gap-4">
                        <p class="flex-1 text-sm text-muted-foreground">{{ providerHint(provider) }}</p>
                        <Button v-if="provider.key === 'github' && provider.available && !repository && can_manage && !project.is_archived" variant="outline" as-child>
                            <Link :href="gitHubUrl">{{ isPl ? 'Skonfiguruj GitHub' : 'Configure GitHub' }} <ArrowRight class="size-4" /></Link>
                        </Button>
                        <p v-else-if="provider.key === 'github' && !repository && !can_manage && !project.is_archived" class="flex items-center gap-2 text-xs text-muted-foreground">
                            <LockKeyhole class="size-4" /> {{ isPl ? 'Wymaga uprawnień administratora przestrzeni.' : 'Requires workspace admin permissions.' }}
                        </p>
                        <span v-else-if="!provider.available" class="flex items-center gap-1.5 text-xs text-muted-foreground"><Server class="size-4" /> {{ isPl ? 'Jeszcze niedostępne' : 'Not available yet' }}</span>
                    </CardContent>
                </Card>
            </div>
        </section>
        <p class="text-sm text-muted-foreground">
            {{ isPl ? 'Konta programistów są powiązane osobno w ustawieniach profilu. W kolejnych etapach dodamy GitLab i Gitea.' : 'Developer identities are managed separately in profile settings. GitLab and Gitea are planned for future stages.' }}
        </p>
    </div>
</template>
