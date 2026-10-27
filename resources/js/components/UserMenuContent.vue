<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Check,
    Languages,
    LogOut,
    Monitor,
    Moon,
    Palette,
    ShieldCheck,
    Sun,
    UserRound,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance, type Appearance } from '@/composables/useAppearance';
import { useI18n } from '@/composables/useI18n';
import { loadLocale } from '@/i18n';
import { getInitials } from '@/composables/useInitials';
import { userAvatarTone } from '@/lib/userAvatarTone';
import { toUrl } from '@/lib/utils';
import LocaleController from '@/actions/App/Http/Controllers/Settings/LocaleController';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { User } from '@/types';

type Props = { user: User };
type LocaleOption = { value: string; label: string };

defineProps<Props>();

const page = usePage();
const { t, locale } = useI18n();
const { appearance, updateAppearance } = useAppearance();
const localeSaving = ref(false);
const currentPath = computed(() => page.url.split('?')[0]);

// The list is shared by Laravel from all valid lang/{locale}.json files.
const localeOptions = computed<LocaleOption[]>(() => {
    const options: unknown = page.props.locales;
    if (!Array.isArray(options)) return [];

    return options.filter((option): option is LocaleOption =>
        option !== null && typeof option === 'object'
        && typeof option.value === 'string'
        && typeof option.label === 'string',
    );
});

const links = computed(() => [
    {
        key: 'profile',
        label: t('settings.profile'),
        description: t('nav.profileHint'),
        href: editProfile(),
        icon: UserRound,
    },
    {
        key: 'security',
        label: t('settings.security'),
        description: t('nav.securityHint'),
        href: editSecurity(),
        icon: ShieldCheck,
    },
]);

function chooseAppearance(value: unknown): void {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value as Appearance);
    }
}

async function chooseLanguage(value: unknown): Promise<void> {
    if (typeof value !== 'string' || localeSaving.value || value === locale.value) return;
    if (!localeOptions.value.some((option) => option.value === value)) return;

    localeSaving.value = true;
    try {
        // Preload before switching, but keep the checkmark on the real server
        // locale. Never render a pending selection as if it were already saved.
        await loadLocale(value);

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!csrfToken) throw new Error('CSRF token unavailable');

        // An ordinary JSON request avoids Inertia redirects and stale page
        // props. The response includes the fresh account locale, so a failed
        // save cannot leave the menu and the main UI showing different choices.
        const response = await fetch(LocaleController.update.url(), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ locale: value }),
        });
        if (!response.ok) throw new Error(`Locale update failed: HTTP ${response.status}`);
        const result: unknown = await response.json();
        if (!result || typeof result !== 'object') throw new Error('Invalid locale update response');
        const saved = result as { locale?: unknown; accountLocale?: unknown };
        if (saved.locale !== value || saved.accountLocale !== value) {
            throw new Error('Server did not confirm the requested locale');
        }

        // A single full reload takes the newly saved server locale and
        // preloads its dictionary before Vue mounts (see resources/js/app.ts).
        window.location.reload();
    } catch (error) {
        console.error('Could not change interface language', error);
        toast.error(t('language.updateFailed'));
    } finally {
        localeSaving.value = false;
    }
}

function handleLogout(): void {
    router.flushAll();
}
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex min-w-0 items-center gap-3 px-3 py-3">
            <Avatar class="size-11 shrink-0 overflow-hidden rounded-full ring-1 ring-border/60">
                <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                <AvatarFallback
                    class="rounded-full text-sm font-semibold"
                    :class="userAvatarTone(user.id)"
                >
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1 space-y-0.5 text-left">
                <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
            </div>
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />

    <DropdownMenuGroup>
        <DropdownMenuItem
            v-for="item in links"
            :key="item.key"
            :as-child="true"
            class="rounded-lg p-0"
        >
            <Link
                class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5"
                :class="currentPath === toUrl(item.href) ? 'bg-accent' : undefined"
                :href="item.href"
                :aria-current="currentPath === toUrl(item.href) ? 'page' : undefined"
                :data-test="`user-menu-${item.key}`"
                prefetch
            >
                <component :is="item.icon" class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                <span class="flex min-w-0 flex-1 flex-col items-start gap-0.5">
                    <span class="text-sm font-medium">{{ item.label }}</span>
                    <span class="text-xs text-muted-foreground">{{ item.description }}</span>
                </span>
            </Link>
        </DropdownMenuItem>

        <DropdownMenuSub>
            <DropdownMenuSubTrigger class="rounded-lg px-3 py-2.5" data-test="user-menu-appearance">
                <Palette class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                <span class="flex min-w-0 flex-1 flex-col items-start gap-0.5">
                    <span class="text-sm font-medium">{{ t('settings.appearance') }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('nav.appearanceHint') }}</span>
                </span>
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
                <DropdownMenuSubContent
                    class="w-72 rounded-xl p-1.5 shadow-lg"
                    :aria-label="t('settings.appearance')"
                    :side-offset="8"
                >
                    <DropdownMenuLabel class="flex items-center gap-3 rounded-lg px-2 py-2 font-normal">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <Palette class="size-4 text-foreground" aria-hidden="true" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ t('settings.appearance') }}</span>
                            <span class="text-xs text-muted-foreground">{{ t('nav.appearanceHint') }}</span>
                        </span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator class="my-1" />
                    <DropdownMenuRadioGroup :model-value="appearance" @update:model-value="chooseAppearance">
                        <DropdownMenuRadioItem
                            value="light"
                            class="gap-3 rounded-lg py-2.5 pr-10 pl-3 [&>span:first-child]:right-3 [&>span:first-child]:left-auto [&>span:first-child]:size-4"
                            data-test="appearance-light"
                        >
                            <Sun class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-sm font-medium">{{ t('appearance.light') }}</span>
                                <span class="text-xs text-muted-foreground">{{ t('nav.appearanceLightHint') }}</span>
                            </span>
                            <template #indicator-icon>
                                <Check class="size-4" aria-hidden="true" />
                            </template>
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem
                            value="dark"
                            class="gap-3 rounded-lg py-2.5 pr-10 pl-3 [&>span:first-child]:right-3 [&>span:first-child]:left-auto [&>span:first-child]:size-4"
                            data-test="appearance-dark"
                        >
                            <Moon class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-sm font-medium">{{ t('appearance.dark') }}</span>
                                <span class="text-xs text-muted-foreground">{{ t('nav.appearanceDarkHint') }}</span>
                            </span>
                            <template #indicator-icon>
                                <Check class="size-4" aria-hidden="true" />
                            </template>
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem
                            value="system"
                            class="gap-3 rounded-lg py-2.5 pr-10 pl-3 [&>span:first-child]:right-3 [&>span:first-child]:left-auto [&>span:first-child]:size-4"
                            data-test="appearance-system"
                        >
                            <Monitor class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-sm font-medium">{{ t('appearance.system') }}</span>
                                <span class="text-xs text-muted-foreground">{{ t('nav.appearanceSystemHint') }}</span>
                            </span>
                            <template #indicator-icon>
                                <Check class="size-4" aria-hidden="true" />
                            </template>
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuSubContent>
            </DropdownMenuPortal>
        </DropdownMenuSub>

        <DropdownMenuSub>
            <DropdownMenuSubTrigger class="rounded-lg px-3 py-2.5" data-test="user-menu-language">
                <Languages class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                <span class="flex min-w-0 flex-1 flex-col items-start gap-0.5">
                    <span class="text-sm font-medium">{{ t('settings.language') }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('nav.languageHint') }}</span>
                </span>
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
                <DropdownMenuSubContent
                    class="w-72 rounded-xl p-1.5 shadow-lg"
                    :aria-label="t('settings.language')"
                    :side-offset="8"
                >
                    <DropdownMenuLabel class="flex items-center gap-3 rounded-lg px-2 py-2 font-normal">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <Languages class="size-4 text-foreground" aria-hidden="true" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ t('settings.language') }}</span>
                            <span class="text-xs text-muted-foreground">{{ t('nav.languageHint') }}</span>
                        </span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator class="my-1" />
                    <DropdownMenuRadioGroup :model-value="locale" @update:model-value="chooseLanguage">
                        <DropdownMenuRadioItem
                            v-for="option in localeOptions"
                            :key="option.value"
                            :value="option.value"
                            :disabled="localeSaving"
                            :data-test="`language-${option.value}`"
                            class="gap-3 rounded-lg py-2.5 pr-10 pl-3 [&>span:first-child]:right-3 [&>span:first-child]:left-auto [&>span:first-child]:size-4"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-background text-[11px] font-semibold uppercase text-muted-foreground"
                                aria-hidden="true"
                            >
                                {{ option.value.split('-')[0] }}
                            </span>
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-sm font-medium">{{ option.label }}</span>
                                <span class="text-xs text-muted-foreground">{{ t('nav.languageOptionHint') }} · {{ option.value }}</span>
                            </span>
                            <template #indicator-icon>
                                <Check class="size-4" aria-hidden="true" />
                            </template>
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuSubContent>
            </DropdownMenuPortal>
        </DropdownMenuSub>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true" class="rounded-lg p-0">
        <Link
            class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="size-5 text-muted-foreground" aria-hidden="true" />
            {{ t('nav.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
