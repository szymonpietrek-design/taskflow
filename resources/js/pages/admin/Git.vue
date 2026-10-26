<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, GitBranch, GitFork, GitMerge, Server } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Provider = {
    key: 'github' | 'gitlab' | 'gitea';
    name: string;
    available: boolean;
    configured: boolean;
};

defineProps<{ providers: Provider[] }>();
const { t } = useI18n();

defineOptions({ layout: { breadcrumbs: [{ title: 'Integracje: Git', href: '/admin/integrations/git' }] } });
</script>

<template>
    <Head :title="t('screens.admin.git.git_integrations')" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-1.5">
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                <GitBranch class="size-6" /> {{ t('screens.admin.git.git_integrations') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('screens.admin.git.configure_git_providers_for_the_entire_taskflow_platfor') }}
            </p>
        </div>

        <section class="space-y-3" aria-labelledby="vcs-admin-providers">
            <div class="space-y-1">
                <h2 id="vcs-admin-providers" class="text-base font-semibold">
                    {{ t('screens.admin.git.repository_providers') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('screens.admin.git.providers_are_configured_globally_while_each_project_ch') }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="provider in providers" :key="provider.key" class="gap-4" :class="!provider.available ? 'bg-muted/20' : ''">
                    <CardHeader class="space-y-5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex size-11 items-center justify-center rounded-lg border bg-background">
                                <GitBranch v-if="provider.key === 'github'" class="size-5" />
                                <GitMerge v-else-if="provider.key === 'gitlab'" class="size-5" />
                                <GitFork v-else class="size-5" />
                            </span>
                            <Badge :variant="provider.configured ? 'secondary' : provider.available ? 'outline' : 'secondary'">
                                {{ provider.configured ? (t('screens.admin.git.configured')) : provider.available ? (t('screens.admin.git.available')) : (t('screens.admin.git.coming_soon')) }}
                            </Badge>
                        </div>
                        <CardTitle class="text-lg">{{ provider.name }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col items-start gap-4">
                        <p class="min-h-12 text-sm text-muted-foreground">
                            {{ provider.key === 'github'
                                ? (t('screens.admin.git.connect_repositories_through_github_app'))
                                : (t('screens.admin.git.support_is_planned_for_a_future_stage')) }}
                        </p>
                        <Button v-if="provider.key === 'github' && provider.available" variant="outline" as-child>
                            <Link href="/admin/integrations/github">
                                {{ provider.configured ? (t('screens.admin.git.manage_github')) : (t('screens.admin.git.configure_github')) }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <span v-else class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Server class="size-4" /> {{ t('screens.admin.git.not_available_yet') }}
                        </span>
                    </CardContent>
                </Card>
            </div>
        </section>
    </div>
</template>
