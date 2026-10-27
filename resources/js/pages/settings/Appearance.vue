<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useI18n } from '@/composables/useI18n';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('appearance.pageTitle')" />

    <h1 class="sr-only">{{ t('appearance.pageTitle') }}</h1>

    <div class="space-y-6">
        <Heading
            :title="t('appearance.heading')"
            :description="t('appearance.description')"
        />
        <AppearanceTabs />
    </div>
</template>
