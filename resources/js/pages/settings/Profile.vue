<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CheckCircle2, ExternalLink, GitBranch, Link2, Trash2 } from '@lucide/vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type GitHubAccount = {
    github_login: string;
    avatar_url: string | null;
    connected_at: string | null;
};

const props = defineProps<{
    githubAccount: GitHubAccount | null;
    githubConnectionConfigured: boolean;
}>();

const disconnectDialogOpen = ref(false);
const disconnecting = ref(false);

function disconnectGitHub(): void {
    if (!props.githubAccount || disconnecting.value) return;

    disconnecting.value = true;
    router.delete('/settings/github', {
        preserveScroll: true,
        onSuccess: () => { disconnectDialogOpen.value = false; },
        onFinish: () => { disconnecting.value = false; },
    });
}

function onDisconnectDialogChange(open: boolean): void {
    if (!disconnecting.value) disconnectDialogOpen.value = open;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t, locale } = useI18n();
const hasVerifiedEmail = computed(() => Boolean(user.value.email_verified_at));
const connectedAtLabel = computed(() => {
    if (!props.githubAccount?.connected_at) return null;
    const date = new Date(props.githubAccount.connected_at);
    return Number.isNaN(date.getTime())
        ? null
        : new Intl.DateTimeFormat(locale.value === 'pl' ? 'pl-PL' : 'en-US', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(date);
});
</script>

<template>
    <Head :title="t('profile.pageTitle')" />

    <h1 class="sr-only">{{ t('profile.pageTitle') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('profile.heading')"
            :description="t('profile.description')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('profile.name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="t('profile.fullName')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('profile.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="t('profile.email')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ t('profile.emailUnverified') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{ t('profile.resendVerification') }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ t('profile.verificationSent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button">
                    {{ t('common.save') }}
                </Button>
            </div>
        </Form>
        <section class="space-y-4 border-t pt-6" aria-labelledby="github-accounts-heading">
            <div>
                <h2 id="github-accounts-heading" class="text-base font-semibold">
                    {{ t('profile.github.title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('profile.github.description') }}
                </p>
            </div>

            <div class="rounded-lg border bg-card p-4 sm:p-5">
                <div v-if="githubAccount" class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <Avatar class="size-11 border">
                            <AvatarImage
                                v-if="githubAccount.avatar_url"
                                :src="githubAccount.avatar_url"
                                :alt="t('profile.github.avatarAlt')"
                            />
                            <AvatarFallback class="text-sm font-semibold">
                                {{ githubAccount.github_login.slice(0, 2).toUpperCase() }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="break-all font-medium" data-test="github-login">
                                    {{ githubAccount.github_login }}
                                </span>
                                <Badge variant="secondary" class="gap-1">
                                    <CheckCircle2 class="size-3.5" />
                                    {{ t('profile.github.verified') }}
                                </Badge>
                            </div>
                            <p v-if="connectedAtLabel" class="text-xs text-muted-foreground">
                                {{ t('profile.github.connectedOn') }} {{ connectedAtLabel }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 border-t pt-4">
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="`https://github.com/${encodeURIComponent(githubAccount.github_login)}`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink class="size-4" />
                                {{ t('profile.github.openProfile') }}
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            data-test="github-disconnect-open"
                            @click="disconnectDialogOpen = true"
                        >
                            <Trash2 class="size-4" />
                            {{ t('profile.github.disconnect') }}
                        </Button>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <GitBranch class="size-5" aria-hidden="true" />
                        </div>
                        <div class="space-y-1">
                            <p class="font-medium">{{ t('profile.github.notConnected') }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('profile.github.optional') }}
                            </p>
                        </div>
                    </div>
                    <Button
                        v-if="githubConnectionConfigured && hasVerifiedEmail"
                        size="sm"
                        as-child
                    >
                        <a href="/settings/github/connect" data-test="github-connect">
                            <Link2 class="size-4" />
                            {{ t('profile.github.connect') }}
                        </a>
                    </Button>
                    <p v-else class="text-sm text-muted-foreground" role="status">
                        {{ t(!hasVerifiedEmail ? 'profile.github.verifyEmail' : 'profile.github.unavailable') }}
                    </p>
                </div>
            </div>
        </section>
    </div>

    <Dialog :open="disconnectDialogOpen" @update:open="onDisconnectDialogChange">
        <DialogContent class="sm:max-w-md" :show-close-button="!disconnecting">
            <DialogHeader>
                <DialogTitle>{{ t('profile.github.confirmTitle') }}</DialogTitle>
                <DialogDescription>{{ t('profile.github.confirmDescription') }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button type="button" variant="outline" :disabled="disconnecting" @click="disconnectDialogOpen = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="disconnecting"
                    data-test="github-disconnect-confirm"
                    @click="disconnectGitHub"
                >
                    {{ t('profile.github.disconnect') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
