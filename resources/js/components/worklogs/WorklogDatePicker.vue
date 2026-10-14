<script setup lang="ts">
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
    today,
    type DateValue,
} from '@internationalized/date';
import { CalendarDays } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

// Keep date-only values in YYYY-MM-DD form. Do not convert the submitted date to UTC.
const props = withDefaults(
    defineProps<{
        modelValue: string;
        maxDate?: string;
        id?: string;
        ariaLabel?: string;
        disabled?: boolean;
        invalid?: boolean;
        placeholder?: string;
    }>(),
    {
        disabled: false,
        invalid: false,
        placeholder: '',
    },
);
const emit = defineEmits<{ 'update:modelValue': [date: string] }>();
const { t, locale } = useI18n();
const formatter = computed(() => new DateFormatter(locale.value === 'pl' ? 'pl-PL' : 'en-US', { dateStyle: 'medium' }));
const selectedDate = computed<DateValue | undefined>({
    get: () => (props.modelValue ? parseDate(props.modelValue) : undefined),
    set: (value) => emit('update:modelValue', value?.toString() ?? ''),
});
const maxValue = computed<DateValue | undefined>(() =>
    props.maxDate ? parseDate(props.maxDate) : undefined,
);
const placeholderDate = computed<DateValue>(
    () => selectedDate.value ?? today(getLocalTimeZone()),
);
const formattedValue = computed(() =>
    selectedDate.value
        ? formatter.value.format(selectedDate.value.toDate(getLocalTimeZone()))
        : (props.placeholder || t('audit.no_date')),
);
</script>

<template>
    <Popover v-slot="{ close }">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                class="w-full min-w-0 justify-between gap-2 font-normal"
                :disabled="disabled"
                :aria-label="ariaLabel ?? t('audit.choose_work_date')"
                :aria-invalid="invalid || undefined"
            >
                <span class="truncate">{{ formattedValue }}</span>
                <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start">
            <Calendar
                v-model="selectedDate"
                :default-placeholder="placeholderDate"
                :max-value="maxValue"
                layout="month-and-year"
                initial-focus
                @update:model-value="
                    (date) => {
                        if (date) close();
                    }
                "
            />
        </PopoverContent>
    </Popover>
</template>
