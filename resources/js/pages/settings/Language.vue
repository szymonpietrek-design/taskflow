<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import LocaleController from '@/actions/App/Http/Controllers/Settings/LocaleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { useI18n } from '@/composables/useI18n';

type LocaleOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    locales: LocaleOption[];
    selectedLocale: string;
}>();

const selected = ref(props.selectedLocale);
const { t } = useI18n();

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
            variant="small"
            :title="t('language.heading')"
            :description="t('language.description')"
        />

        <Form
            v-bind="LocaleController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="locale">{{ t('language.field') }}</Label>
                <NativeSelect
                    id="locale"
                    name="locale"
                    class="w-full"
                    v-model="selected"
                >
                    <NativeSelectOption
                        v-for="localeOption in locales"
                        :key="localeOption.value"
                        :value="localeOption.value"
                    >
                        {{ localeOption.label }}
                    </NativeSelectOption>
                </NativeSelect>
                <InputError :message="errors.locale" />
                <p class="text-sm text-muted-foreground">
                    {{ t('language.note') }}
                </p>
            </div>

            <Button :disabled="processing">
                {{ t('common.save') }}
            </Button>
        </Form>
    </div>
</template>
