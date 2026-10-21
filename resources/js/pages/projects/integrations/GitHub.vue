<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, GitBranch, LockKeyhole, ShieldCheck, Trash2 } from '@lucide/vue';
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
const props = defineProps<{
    project: { id: number; name: string; is_archived: boolean };
    configured: boolean;
    can_connect: boolean;
    repository: Repository | null;
    installations: { installation_id: number; account_login: string }[];
}>();
const { locale } = useI18n();
const isPl = computed(() => locale.value === 'pl');
const base = computed(() => `/projects/${props.project.id}/integrations/github`);
const form = useForm({ installation_id: props.installations.length === 1 ? String(props.installations[0].installation_id) : '', repository: '' });
const disconnecting = ref(false);
const confirmDisconnect = ref(false);

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
            <Link :href="`/projects/${project.id}`" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ isPl ? 'Wróć do projektu' : 'Back to project' }}
            </Link>
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight"><GitBranch class="size-6" /> GitHub</h1>
            <p class="text-sm text-muted-foreground">
                {{ isPl ? 'Repozytorium powiązane z projektem' : 'Repository linked to this project' }} · {{ project.name }}
            </p>
        </div>
        <div v-if="project.is_archived" class="rounded-lg border p-3 text-sm text-muted-foreground">
            {{ isPl ? 'Projekt jest zarchiwizowany. Integracja działa tylko do odczytu.' : 'The project is archived. This integration is read-only.' }}
        </div>
        <Card class="gap-3">
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <ShieldCheck class="size-4" /> {{ isPl ? 'Połączenie repozytorium' : 'Repository connection' }}
                </CardTitle>
                <p class="text-sm text-muted-foreground">
                    {{ isPl ? 'Pierwsze połączenie zapisuje obecny commit jako punkt startowy. Nie pobiera wcześniejszych zmian i nie uruchamia AI.' : 'The first connection stores the current commit as a baseline. No earlier history or AI analysis is started.' }}
                </p>
            </CardHeader>
            <CardContent class="space-y-5">
                <template v-if="repository">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{{ isPl ? 'Połączono' : 'Connected' }}</Badge>
                        <a :href="`https://github.com/${repository.full_name}`" target="_blank" rel="noopener noreferrer" class="font-medium underline underline-offset-4">
                            {{ repository.full_name }}
                        </a>
                    </div>
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Instalacja GitHub App' : 'GitHub App installation' }}</dt><dd class="font-medium">{{ repository.account_login }} · #{{ repository.installation_id }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Gałąź' : 'Branch' }}</dt><dd class="font-medium">{{ repository.default_branch }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Punkt startowy (SHA)' : 'Starting SHA' }}</dt><dd class="font-mono text-xs break-all">{{ repository.start_sha }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Ostatni zsynchronizowany SHA' : 'Last synced SHA' }}</dt><dd class="font-mono text-xs break-all">{{ repository.last_synced_sha }}</dd></div>
                        <div class="space-y-1"><dt class="text-muted-foreground">{{ isPl ? 'Ostatnia synchronizacja' : 'Last synchronization' }}</dt><dd>{{ repository.last_synced_at || (isPl ? 'Jeszcze nie uruchamiano' : 'Not started yet') }}</dd></div>
                    </dl>
                    <div v-if="can_connect" class="border-t pt-4">
                        <Button v-if="!confirmDisconnect" type="button" variant="outline" @click="confirmDisconnect = true">
                            <Trash2 class="size-4" /> {{ isPl ? 'Odłącz repozytorium' : 'Disconnect repository' }}
                        </Button>
                        <div v-else class="flex flex-wrap items-center gap-3">
                            <p class="text-sm text-muted-foreground">{{ isPl ? 'Potwierdź odłączenie repozytorium od projektu.' : 'Confirm disconnecting this repository.' }}</p>
                            <Button type="button" variant="destructive" :disabled="disconnecting" @click="disconnect">{{ isPl ? 'Odłącz' : 'Disconnect' }}</Button>
                            <Button type="button" variant="outline" @click="confirmDisconnect = false">{{ isPl ? 'Anuluj' : 'Cancel' }}</Button>
                        </div>
                    </div>
                </template>
                <div v-else-if="!can_connect" class="flex items-start gap-2 text-sm text-muted-foreground">
                    <LockKeyhole class="mt-0.5 size-4 shrink-0" />
                    <p>{{ isPl ? 'Tylko właściciel i administratorzy przestrzeni mogą podłączyć repozytorium.' : 'Only workspace owners and administrators can connect a repository.' }}</p>
                </div>
                <div v-else-if="!configured" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    {{ isPl ? 'GitHub App nie jest jeszcze skonfigurowana. Administrator systemu może ją skonfigurować w panelu Integracje → GitHub.' : 'GitHub App is not configured. A system administrator can configure it under Integrations → GitHub.' }}
                </div>
                <form v-else class="max-w-xl space-y-3" @submit.prevent="connect">
                    <label for="github-installation" class="block text-sm font-medium">Installation ID</label>
                    <Input id="github-installation" v-model="form.installation_id" required inputmode="numeric" pattern="[0-9]+" maxlength="20" placeholder="12345678" autocomplete="off" :aria-invalid="!!form.errors.installation_id" />
                    <p v-if="form.errors.installation_id" class="text-sm text-destructive">{{ form.errors.installation_id }}</p>
                    <p class="text-xs text-muted-foreground">{{ isPl ? 'Numer z adresu github.com/settings/installations/…, zapisywany w bazie danych, nie w .env.' : 'Number from github.com/settings/installations/…, stored in the database, not .env.' }}</p>
                    <p class="text-xs text-muted-foreground">{{ isPl ? 'Nową instalację może zatwierdzić administrator systemu. Pozostali administratorzy przestrzeni mogą używać już zatwierdzonych instalacji.' : 'Only a system administrator can approve a new installation. Workspace administrators may reuse approved installations.' }}</p>
                    <div v-if="installations.length" class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>{{ isPl ? 'Instalacje w tej przestrzeni:' : 'Installations in this workspace:' }}</span>
                        <Button v-for="item in installations" :key="item.installation_id" type="button" size="sm" variant="outline" @click="form.installation_id = String(item.installation_id)">
                            {{ item.account_login }} · #{{ item.installation_id }}
                        </Button>
                    </div>
                    <label for="github-repository" class="block text-sm font-medium">{{ isPl ? 'Repozytorium GitHub' : 'GitHub repository' }}</label>
                    <Input id="github-repository" v-model="form.repository" required maxlength="200" placeholder="owner/repository" autocomplete="off" :aria-invalid="!!form.errors.repository" />
                    <p v-if="form.errors.repository" class="text-sm text-destructive">{{ form.errors.repository }}</p>
                    <p class="text-xs text-muted-foreground">{{ isPl ? 'Repozytorium musi być udostępnione zainstalowanej aplikacji GitHub App.' : 'The repository must be accessible to the installed GitHub App.' }}</p>
                    <Button type="submit" :disabled="form.processing || !form.repository.trim() || !form.installation_id.trim()"><GitBranch class="size-4" /> {{ isPl ? 'Połącz repozytorium' : 'Connect repository' }}</Button>
                </form>
            </CardContent>
        </Card>
        <p class="text-sm text-muted-foreground">
            {{ isPl ? 'Synchronizacja commitów, pull requestów i analiza przez Agenta AI będą dostępne w kolejnych etapach.' : 'Commit/PR synchronization and AI analysis will be added in later stages.' }}
        </p>
    </div>
</template>
