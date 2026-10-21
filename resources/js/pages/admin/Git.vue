<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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
const { locale } = useI18n();
const isPl = computed(() => locale.value === 'pl');

defineOptions({ layout: { breadcrumbs: [{ title: 'Integracje: Git', href: '/admin/integrations/git' }] } });
</script>

<template>
    <Head :title="isPl ? 'Integracje: Git' : 'Git integrations'" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-1.5">
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                <GitBranch class="size-6" /> {{ isPl ? 'Integracje: Git' : 'Git integrations' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ isPl ? 'Konfiguracja dostawców Git dla całej platformy TaskFlow.' : 'Configure Git providers for the entire TaskFlow platform.' }}
            </p>
        </div>

        <section class="space-y-3" aria-labelledby="vcs-admin-providers">
            <div class="space-y-1">
                <h2 id="vcs-admin-providers" class="text-base font-semibold">
                    {{ isPl ? 'Dostawcy repozytoriów' : 'Repository providers' }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ isPl ? 'Dostawcy są konfigurowani globalnie, ale każdy projekt wybiera własne repozytorium. Nie trzeba ograniczać się do jednej usługi.' : 'Providers are configured globally, while each project chooses its own repository. Different projects can use different services.' }}
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
                                {{ provider.configured ? (isPl ? 'Skonfigurowano' : 'Configured') : provider.available ? (isPl ? 'Dostępny' : 'Available') : (isPl ? 'W przygotowaniu' : 'Coming soon') }}
                            </Badge>
                        </div>
                        <CardTitle class="text-lg">{{ provider.name }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col items-start gap-4">
                        <p class="min-h-12 text-sm text-muted-foreground">
                            {{ provider.key === 'github'
                                ? (isPl ? 'Połączenia repozytoriów przez GitHub App.' : 'Connect repositories through GitHub App.')
                                : (isPl ? 'Obsługa zostanie dodana w kolejnym etapie.' : 'Support is planned for a future stage.') }}
                        </p>
                        <Button v-if="provider.key === 'github' && provider.available" variant="outline" as-child>
                            <Link href="/admin/integrations/github">
                                {{ provider.configured ? (isPl ? 'Zarządzaj GitHub' : 'Manage GitHub') : (isPl ? 'Skonfiguruj GitHub' : 'Configure GitHub') }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <span v-else class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Server class="size-4" /> {{ isPl ? 'Jeszcze niedostępne' : 'Not available yet' }}
                        </span>
                    </CardContent>
                </Card>
            </div>
        </section>
    </div>
</template>
