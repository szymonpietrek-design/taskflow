<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import LocaleController from '@/actions/App/Http/Controllers/Settings/LocaleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';

const props = defineProps<{
    locales: Array<{ value: string; label: string }>;
    selectedLocale: string;
}>();

const selected = ref(props.selectedLocale);
const processing = ref(false);
const error = ref<string | undefined>();
const { t } = useI18n();

watch(() => props.selectedLocale, (value) => { selected.value = value; });

function chooseLanguage(value: string): void {
    if (processing.value || value === selected.value) return;

    const previous = selected.value;
    selected.value = value;
    error.value = undefined;
    processing.value = true;

    router.patch(LocaleController.update.url(), { locale: value }, {
        preserveScroll: true,
        onError: (errors) => {
            selected.value = previous;
            error.value = errors.locale;
        },
        onFinish: () => { processing.value = false; },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Language settings',
                href: '/settings/language',
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('language.pageTitle')" />

    <h1 class="sr-only">{{ t('language.pageTitle') }}</h1>

    <div class="space-y-6">
        <Heading
            :title="t('language.heading')"
            :description="t('language.description')"
        />

        <div class="grid max-w-xs gap-2">
            <Label for="locale">{{ t('language.field') }}</Label>
            <LanguageSwitcher
                id="locale"
                :model-value="selected"
                :options="locales"
                :disabled="processing"
                @update:model-value="chooseLanguage"
            />
            <InputError :message="error" />
            <p class="text-sm text-muted-foreground">
                {{ t('language.note') }}
            </p>
        </div>
    </div>
</template>
