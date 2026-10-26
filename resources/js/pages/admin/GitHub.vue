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
    settings: { app_id: string; has_private_key: boolean; client_id: string; has_client_secret: boolean; updated_at: string | null };
    callback_url: string;
}>();

const { t } = useI18n();
const fileInput = ref<HTMLInputElement | null>(null);
const chosenName = ref('');
const form = useForm<{ app_id: string; private_key: File | null; client_id: string; client_secret: string }>({
    app_id: props.settings.app_id,
    private_key: null,
    client_id: props.settings.client_id,
    client_secret: '',
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
            form.client_secret = '';
            chosenName.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

defineOptions({ layout: { breadcrumbs: [{ title: 'Integracje: Git', href: '/admin/integrations/git' }, { title: 'GitHub', href: '/admin/integrations/github' }] } });
</script>

<template>
    <Head :title="t('screens.admin.github.integrations_github')" />
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-2">
            <Link href="/admin/integrations/git" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" /> {{ t('screens.admin.github.back_to_git_integrations') }}
            </Link>
            <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight"><GitBranch class="size-6" /> {{ t('screens.admin.github.integrations_github') }}</h1>
            <p class="text-sm text-muted-foreground">{{ t('screens.admin.github.server_wide_github_app_configuration_accessible_only_to') }}</p>
        </div>

        <Card class="gap-4">
            <CardHeader class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle class="flex items-center gap-2 text-base"><ShieldCheck class="size-4" /> GitHub App</CardTitle>
                    <Badge v-if="settings.has_private_key" variant="secondary"><CheckCircle2 class="size-3" /> {{ t('screens.admin.github.configured') }}</Badge>
                    <Badge v-else variant="outline">{{ t('screens.admin.github.not_configured') }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">{{ t('screens.admin.github.the_private_key_is_encrypted_on_the_server_it_is_never') }}</p>
            </CardHeader>
            <CardContent>
                <form class="max-w-xl space-y-5" @submit.prevent="save">
                    <div class="space-y-2">
                        <label for="github-app-id" class="text-sm font-medium">GitHub App ID</label>
                        <Input id="github-app-id" v-model="form.app_id" required inputmode="numeric" pattern="[0-9]+" autocomplete="off" placeholder="1234567" :aria-invalid="!!form.errors.app_id" />
                        <p v-if="form.errors.app_id" class="text-sm text-destructive">{{ form.errors.app_id }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('screens.admin.github.find_it_in_github_developer_settings_github_apps_genera') }}</p>
                    </div>
                    <div class="space-y-2">
                        <label for="github-private-key" class="text-sm font-medium">{{ t('screens.admin.github.github_app_private_key_pem') }}</label>
                        <input id="github-private-key" ref="fileInput" type="file" accept=".pem" class="sr-only" @change="selectKey" />
                        <div class="rounded-lg border border-dashed p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted"><LockKeyhole class="size-5 text-muted-foreground" /></div>
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p class="break-all text-sm font-medium">{{ chosenName || (settings.has_private_key ? (t('screens.admin.github.private_key_stored_securely')) : (t('screens.admin.github.no_key_uploaded_yet'))) }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('screens.admin.github.choose_the_pem_downloaded_from_github_max_32_kb_uploadi') }}</p>
                                </div>
                                <Button type="button" variant="outline" @click="fileInput?.click()"><Upload class="size-4" /> {{ t('screens.admin.github.choose_file') }}</Button>
                            </div>
                        </div>
                        <p v-if="form.errors.private_key" class="text-sm text-destructive">{{ form.errors.private_key }}</p>
                        <p v-if="settings.has_private_key" class="text-xs text-muted-foreground">{{ t('screens.admin.github.you_do_not_need_to_upload_the_key_again_to_edit_app_id') }}</p>
                    </div>
                    <div class="space-y-3 border-t pt-5">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold">{{ t('screens.admin.github.github_user_authorization') }}</p>
                            <p class="text-xs text-muted-foreground">{{ t('screens.admin.github.optional_identity_verification_with_the_same_github_app') }}</p>
                        </div>
                        <div class="space-y-2">
                            <label for="github-client-id" class="text-sm font-medium">Client ID</label>
                            <Input id="github-client-id" v-model="form.client_id" maxlength="150" autocomplete="off" placeholder="Iv1.…" :aria-invalid="!!form.errors.client_id" />
                            <p v-if="form.errors.client_id" class="text-sm text-destructive">{{ form.errors.client_id }}</p>
                            <p class="text-xs text-muted-foreground">{{ t('screens.admin.github.client_id_from_github_app_general_different_from_app_id') }}</p>
                        </div>
                        <div class="space-y-2">
                            <label for="github-client-secret" class="text-sm font-medium">Client Secret</label>
                            <Input id="github-client-secret" v-model="form.client_secret" type="password" maxlength="255" autocomplete="new-password" :placeholder="settings.has_client_secret ? (t('screens.admin.github.secret_stored_leave_blank_to_keep')) : (t('screens.admin.github.enter_client_secret'))" :aria-invalid="!!form.errors.client_secret" />
                            <p v-if="form.errors.client_secret" class="text-sm text-destructive">{{ form.errors.client_secret }}</p>
                            <p class="text-xs text-muted-foreground">{{ t('screens.admin.github.encrypted_in_the_database_and_never_sent_back_to_the_br') }}</p>
                        </div>
                        <div class="rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground">
                            <p class="font-medium text-foreground">GitHub App → Callback URL</p>
                            <code class="mt-1 block select-all break-all">{{ callback_url }}</code>
                            <p class="mt-1">{{ t('screens.admin.github.register_the_complete_callback_url_in_github_app_settin') }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 border-t pt-4">
                        <Button type="submit" :disabled="form.processing || !form.app_id.trim() || (!settings.has_private_key && !form.private_key)">
                            {{ t('screens.admin.github.save_configuration') }}
                        </Button>
                        <span class="text-xs text-muted-foreground">{{ t('screens.admin.github.the_key_and_tokens_never_reach_vue') }}</span>
                    </div>
                </form>
            </CardContent>
        </Card>
        <p class="text-sm text-muted-foreground">
            {{ t('screens.admin.github.after_saving_open_a_project_git_integrations_to_connect') }}
        </p>
    </div>
</template>
