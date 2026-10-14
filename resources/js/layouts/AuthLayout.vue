<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
const { translateLiteral } = useI18n();
</script>

<template>
    <AuthLayout :title="translateLiteral(title)" :description="translateLiteral(description)">
        <slot />
    </AuthLayout>
</template>
