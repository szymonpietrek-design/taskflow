<script setup lang="ts">
import { DatePicker } from '@/components/ui/date-picker';
import { useI18n } from '@/composables/useI18n';

// Backward-compatible worklog wrapper for the shared shadcn-vue DatePicker.
const props = withDefaults(defineProps<{
    modelValue: string;
    maxDate?: string;
    id?: string;
    ariaLabel?: string;
    disabled?: boolean;
    invalid?: boolean;
    placeholder?: string;
}>(), {
    disabled: false,
    invalid: false,
    placeholder: '',
});
const emit = defineEmits<{ 'update:modelValue': [date: string] }>();
const { t } = useI18n();
</script>

<template>
    <DatePicker
        :model-value="props.modelValue"
        :max-date="props.maxDate"
        :id="props.id"
        :aria-label="props.ariaLabel ?? t('audit.choose_work_date')"
        :disabled="props.disabled"
        :invalid="props.invalid"
        :placeholder="props.placeholder"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
