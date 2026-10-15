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
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

// A date-only picker. The model always contains YYYY-MM-DD or an empty string.
// Keep the selected calendar date out of UTC conversions and POST it as a hidden field.
const props = withDefaults(defineProps<{
    modelValue: string;
    id?: string;
    ariaLabel?: string;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    clearable?: boolean;
    maxDate?: string;
}>(), {
    disabled: false,
    invalid: false,
    clearable: false,
    placeholder: '',
});
const emit = defineEmits<{ 'update:modelValue': [date: string] }>();
const { locale, t } = useI18n();
const dateFormatter = computed(() => new DateFormatter(
    locale.value === 'pl' ? 'pl-PL' : 'en-US',
    { dateStyle: 'medium' },
));
const selectedDate = computed<DateValue | undefined>({
    get: () => props.modelValue ? parseDate(props.modelValue) : undefined,
    set: (value) => emit('update:modelValue', value?.toString() ?? ''),
});
const maxValue = computed<DateValue | undefined>(() =>
    props.maxDate ? parseDate(props.maxDate) : undefined,
);
const calendarPlaceholder = computed<DateValue>(
    () => selectedDate.value ?? today(getLocalTimeZone()),
);
const displayDate = computed(() => selectedDate.value
    ? dateFormatter.value.format(selectedDate.value.toDate(getLocalTimeZone()))
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
                class="w-full min-w-0 justify-between gap-2 text-left font-normal"
                :disabled="disabled"
                :aria-label="ariaLabel ?? t('audit.planned_date')"
                :aria-invalid="invalid || undefined"
            >
                <span class="truncate" :class="{ 'text-muted-foreground': !selectedDate }">{{ displayDate }}</span>
                <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
            </Button>
        </PopoverTrigger>
        <PopoverContent align="start" class="w-auto p-0">
            <Calendar
                v-model="selectedDate"
                :default-placeholder="calendarPlaceholder"
                :max-value="maxValue"
                layout="month-and-year"
                initial-focus
                @update:model-value="(date) => { if (date) close(); }"
            />
            <div v-if="clearable" class="border-t p-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="w-full"
                    :disabled="!selectedDate"
                    @click="selectedDate = undefined; close()"
                >{{ t('audit.clear_date') }}</Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
