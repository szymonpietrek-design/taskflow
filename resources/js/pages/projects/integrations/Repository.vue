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
const { locale, t } = useI18n();
const gitHubUrl = computed(() => `/projects/${props.project.id}/integrations/github`);
const currentProvider = computed(() => props.providers.find((item) => item.key === props.repository?.provider));
const formatter = computed(() => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }));
function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : formatter.value.format(date);
}
function providerHint(provider: Provider): string {
    if (!provider.available) return t('screens.project.repository.support_will_be_added_in_a_future_stage');
    if (!provider.configured) return t('screens.project.repository.a_system_administrator_must_configure_github_app');
    return t('screens.project.repository.repositories_connected_through_github_app');
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`${t('screens.project.repository.git_integrations')} · ${project.name}`" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-2">
            <Link :href="`/projects/${project.id}`" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ t('screens.project.repository.back_to_project') }}
            </Link>
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                <GitBranch class="size-6" /> {{ t('screens.project.repository.git_integrations') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('screens.project.repository.connect_a_repository_to_your_project') }} · {{ project.name }}
            </p>
        </div>

        <div v-if="project.is_archived" class="flex items-start gap-2 rounded-lg border p-3 text-sm text-muted-foreground">
            <LockKeyhole class="mt-0.5 size-4 shrink-0" />
            {{ t('screens.project.repository.archived_project_the_integration_is_read_only') }}
        </div>

        <Card v-if="repository" class="gap-3">
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <ShieldCheck class="size-4" /> {{ t('screens.project.repository.connected_repository') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{{ currentProvider?.name ?? repository.provider ?? 'VCS' }}</Badge>
                    <span class="break-all font-medium">{{ repository.full_name }}</span>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ t('screens.project.repository.branch') }}</dt>
                        <dd class="font-medium">{{ repository.default_branch }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ t('screens.project.repository.stored_commits') }}</dt>
                        <dd class="font-medium tabular-nums">{{ repository.commits_count }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-muted-foreground">{{ t('screens.project.repository.last_synchronization') }}</dt>
                        <dd>{{ formatDate(repository.last_synced_at) }}</dd>
                    </div>
                </dl>
                <Button v-if="repository.provider === 'github'" variant="outline" as-child>
                    <Link :href="gitHubUrl">
                        {{ t('screens.project.repository.manage_github_repository') }}
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('screens.project.repository.this_provider_does_not_have_a_management_interface_yet') }}
                </p>
            </CardContent>
        </Card>

        <section v-if="!repository" class="space-y-3" aria-labelledby="vcs-providers-title">
            <div class="space-y-1">
                <h2 id="vcs-providers-title" class="text-base font-semibold">
                    {{ t('screens.project.repository.repository_providers') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('screens.project.repository.choose_a_provider_for_this_project_other_projects_may_u') }}
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
                            <Badge variant="secondary">{{ provider.available ? (t('screens.project.repository.available')) : (t('screens.project.repository.coming_soon')) }}</Badge>
                        </div>
                        <CardTitle class="text-base">{{ provider.name }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col items-start gap-4">
                        <p class="flex-1 text-sm text-muted-foreground">{{ providerHint(provider) }}</p>
                        <Button v-if="provider.key === 'github' && provider.available && !repository && can_manage && !project.is_archived" variant="outline" as-child>
                            <Link :href="gitHubUrl">{{ t('screens.project.repository.configure_github') }} <ArrowRight class="size-4" /></Link>
                        </Button>
                        <p v-else-if="provider.key === 'github' && !repository && !can_manage && !project.is_archived" class="flex items-center gap-2 text-xs text-muted-foreground">
                            <LockKeyhole class="size-4" /> {{ t('screens.project.repository.requires_workspace_admin_permissions') }}
                        </p>
                        <span v-else-if="!provider.available" class="flex items-center gap-1.5 text-xs text-muted-foreground"><Server class="size-4" /> {{ t('screens.project.repository.not_available_yet') }}</span>
                    </CardContent>
                </Card>
            </div>
        </section>
        <p class="text-sm text-muted-foreground">
            {{ t('screens.project.repository.developer_identities_are_managed_separately_in_profile') }}
        </p>
    </div>
</template>
