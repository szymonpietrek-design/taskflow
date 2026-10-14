<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const { locale } = useI18n();
function chooseLanguage(language: 'pl' | 'en') {
    if (language !== locale.value) {
        router.post('/ui-locale', { locale: language }, { preserveState: false, preserveScroll: true });
    }
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
                <nav v-if="!page.props.auth.user" class="flex justify-center gap-2 text-xs text-muted-foreground" aria-label="Language / Język">
                    <button type="button" class="rounded px-2 py-1 hover:text-foreground" :class="locale === 'en' ? 'font-semibold text-foreground underline underline-offset-4' : ''" :aria-current="locale === 'en' ? 'true' : undefined" @click="chooseLanguage('en')">English</button>
                    <span aria-hidden="true">·</span>
                    <button type="button" class="rounded px-2 py-1 hover:text-foreground" :class="locale === 'pl' ? 'font-semibold text-foreground underline underline-offset-4' : ''" :aria-current="locale === 'pl' ? 'true' : undefined" @click="chooseLanguage('pl')">Polski</button>
                </nav>
            </div>
        </div>
    </div>
</template>
