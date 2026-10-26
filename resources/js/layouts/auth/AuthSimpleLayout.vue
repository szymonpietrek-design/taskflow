<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/composables/useI18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const { locale, t } = useI18n();
const processing = ref(false);

function chooseLanguage(language: string): void {
    if (processing.value || language === locale.value) return;

    processing.value = true;
    router.post('/ui-locale', { locale: language }, {
        preserveState: false,
        preserveScroll: true,
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                        >
                            <AppLogoIcon
                                class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
                <div v-if="!page.props.auth.user" class="flex flex-col items-center gap-2">
                    <div class="flex w-44 max-w-full flex-col gap-1.5">
                        <label for="guest-language" class="text-sm font-medium">
                            {{ t('language.field') }}
                        </label>
                        <LanguageSwitcher
                            id="guest-language"
                            :model-value="locale"
                            :options="page.props.locales"
                            :disabled="processing"
                            compact
                            @update:model-value="chooseLanguage"
                        />
                    </div>
                    <p class="max-w-xs text-center text-xs text-muted-foreground">
                        {{ t('language.guestNote') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
