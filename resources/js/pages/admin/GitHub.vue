<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, CheckCircle2, GitBranch, LockKeyhole, ShieldCheck, Upload } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    settings: { app_id: string; has_private_key: boolean; updated_at: string | null };
}>();

const { locale } = useI18n();
const isPl = computed(() => locale.value === 'pl');
const fileInput = ref<HTMLInputElement | null>(null);
const chosenName = ref('');
const form = useForm<{ app_id: string; private_key: File | null }>({
    app_id: props.settings.app_id,
    private_key: null,
});

function selectKey(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.private_key = target.files?.[0] ?? null;
    chosenName.value = form.private_key?.name ?? '';
    form.clearErrors('private_key');
}

function save(): void {
    if (form.processing) return;
    form.post('/admin/integrations/github', {
        preserveScroll: true,
        onSuccess: () => {
            form.private_key = null;
            chosenName.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

defineOptions({ layout: { breadcrumbs: [{ title: 'GitHub', href: '/admin/integrations/github' }] } });
</script>

<template>
    <Head :title="isPl ? 'Integracje · GitHub' : 'Integrations · GitHub'" />
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-2">
            <Link href="/dashboard" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ isPl ? 'Wróć do pulpitu' : 'Back to dashboard' }}
            </Link>
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight"><GitBranch class="size-6" /> {{ isPl ? 'Integracje · GitHub' : 'Integrations · GitHub' }}</h1>
            <p class="text-sm text-muted-foreground">{{ isPl ? 'Globalna konfiguracja GitHub App. Dostęp ma wyłącznie administrator systemu.' : 'Server-wide GitHub App configuration. Accessible only to system administrators.' }}</p>
        </div>

        <Card class="gap-4">
            <CardHeader class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle class="flex items-center gap-2 text-base"><ShieldCheck class="size-4" /> GitHub App</CardTitle>
                    <Badge v-if="settings.has_private_key" variant="secondary"><CheckCircle2 class="size-3" /> {{ isPl ? 'Skonfigurowano' : 'Configured' }}</Badge>
                    <Badge v-else variant="outline">{{ isPl ? 'Nie skonfigurowano' : 'Not configured' }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">{{ isPl ? 'Klucz jest szyfrowany po stronie serwera. Nigdy nie będzie wyświetlany ani pobierany z panelu.' : 'The private key is encrypted on the server. It is never exposed or downloadable through this page.' }}</p>
            </CardHeader>
            <CardContent>
                <form class="max-w-xl space-y-5" @submit.prevent="save">
                    <div class="space-y-2">
                        <label for="github-app-id" class="text-sm font-medium">GitHub App ID</label>
                        <Input id="github-app-id" v-model="form.app_id" required inputmode="numeric" pattern="[0-9]+" autocomplete="off" placeholder="1234567" :aria-invalid="!!form.errors.app_id" />
                        <p v-if="form.errors.app_id" class="text-sm text-destructive">{{ form.errors.app_id }}</p>
                        <p class="text-xs text-muted-foreground">{{ isPl ? 'Znajdziesz go w GitHub → Developer settings → GitHub Apps → General. Nie jest to Client ID.' : 'Find it in GitHub → Developer settings → GitHub Apps → General. It is not the Client ID.' }}</p>
                    </div>
                    <div class="space-y-2">
                        <label for="github-private-key" class="text-sm font-medium">{{ isPl ? 'Klucz prywatny GitHub App (.pem)' : 'GitHub App private key (.pem)' }}</label>
                        <input id="github-private-key" ref="fileInput" type="file" accept=".pem" class="sr-only" @change="selectKey" />
                        <div class="rounded-lg border border-dashed p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted"><LockKeyhole class="size-5 text-muted-foreground" /></div>
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p class="break-all text-sm font-medium">{{ chosenName || (settings.has_private_key ? (isPl ? 'Klucz jest bezpiecznie zapisany' : 'Private key stored securely') : (isPl ? 'Nie przesłano jeszcze klucza' : 'No key uploaded yet')) }}</p>
                                    <p class="text-xs text-muted-foreground">{{ isPl ? 'Wybierz plik .pem pobrany z GitHuba (maks. 32 KB). Przesłanie nowego zastąpi poprzedni klucz.' : 'Choose the .pem downloaded from GitHub (max 32 KB). Uploading a new key replaces the previous one.' }}</p>
                                </div>
                                <Button type="button" variant="outline" @click="fileInput?.click()"><Upload class="size-4" /> {{ isPl ? 'Wybierz plik' : 'Choose file' }}</Button>
                            </div>
                        </div>
                        <p v-if="form.errors.private_key" class="text-sm text-destructive">{{ form.errors.private_key }}</p>
                        <p v-if="settings.has_private_key" class="text-xs text-muted-foreground">{{ isPl ? 'Nie musisz ponownie przesyłać klucza przy zmianie App ID.' : 'You do not need to upload the key again to edit App ID.' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 border-t pt-4">
                        <Button type="submit" :disabled="form.processing || !form.app_id.trim() || (!settings.has_private_key && !form.private_key)">
                            {{ isPl ? 'Zapisz konfigurację' : 'Save configuration' }}
                        </Button>
                        <span class="text-xs text-muted-foreground">{{ isPl ? 'Klucz i tokeny nigdy nie trafiają do Vue.' : 'The key and tokens never reach Vue.' }}</span>
                    </div>
                </form>
            </CardContent>
        </Card>
        <p class="text-sm text-muted-foreground">
            {{ isPl ? 'Po zapisaniu konfiguracji przejdź do projektu → GitHub, aby podać Installation ID i podłączyć repozytorium. Pierwsze połączenie nie importuje historii ani nie uruchamia AI.' : 'After saving, open a project → GitHub to enter the Installation ID and connect a repository. Initial connection imports no history and does not run AI.' }}
        </p>
    </div>
</template>
