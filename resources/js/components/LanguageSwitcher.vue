<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';

const props = withDefaults(defineProps<{
    modelValue: string;
    options: Array<{ value: string; label: string }>;
    id?: string;
    disabled?: boolean;
    compact?: boolean;
}>(), {
    disabled: false,
    compact: false,
});

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const { t } = useI18n();

function changeLanguage(value: unknown): void {
    if (typeof value !== 'string' || value === props.modelValue) return;
    if (!props.options.some((option) => option.value === value)) return;

    emit('update:modelValue', value);
}
</script>

<template>
    <Select
        :model-value="modelValue"
        :disabled="disabled || options.length === 0"
        @update:model-value="changeLanguage"
    >
        <SelectTrigger
            :id="id"
            :aria-label="t('language.field')"
            :size="compact ? 'sm' : 'default'"
            :class="compact ? 'w-44 max-w-full' : 'w-full'"
        >
            <SelectValue :placeholder="t('language.field')" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
