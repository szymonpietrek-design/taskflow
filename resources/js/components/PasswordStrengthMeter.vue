<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle, Check, Circle } from '@lucide/vue';
import { useI18n } from '@/composables/useI18n';
import { evaluatePasswordStrength } from '@/lib/passwordStrength';

const props = defineProps<{ password: string }>();
const { t } = useI18n();
const strength = computed(() => evaluatePasswordStrength(props.password));

const tiers = ['adminUsers.passwordWeak', 'adminUsers.passwordFair', 'adminUsers.passwordGood', 'adminUsers.passwordStrong'] as const;
const tones = ['bg-destructive', 'bg-orange-500', 'bg-sky-500', 'bg-emerald-600'] as const;
const strengthLabel = computed(() => t(tiers[strength.value.level - 1] ?? tiers[0]));
const strengthTone = computed(() => tones[strength.value.level - 1] ?? tones[0]);
const criteria = computed(() => ([
    { key: 'length', label: 'adminUsers.passwordCriteriaLength' },
    { key: 'lowercase', label: 'adminUsers.passwordCriteriaLowercase' },
    { key: 'uppercase', label: 'adminUsers.passwordCriteriaUppercase' },
    { key: 'number', label: 'adminUsers.passwordCriteriaNumber' },
    { key: 'symbol', label: 'adminUsers.passwordCriteriaSymbol' },
] as const).map(item => ({ ...item, met: strength.value.criteria[item.key] })));
</script>

<template>
    <div class="space-y-2" aria-live="polite">
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="text-muted-foreground">{{ t('adminUsers.passwordStrength') }}</span>
            <span v-if="password" class="font-medium">{{ strengthLabel }}</span>
        </div>
        <div
            class="grid grid-cols-4 gap-1.5"
            role="meter"
            :aria-label="t('adminUsers.passwordStrength')"
            :aria-valuenow="strength.level"
            :aria-valuemin="0"
            :aria-valuemax="4"
            :aria-valuetext="password ? strengthLabel : '0 / 4'"
        >
            <span v-for="step in 4" :key="step" class="h-1.5 rounded-full" :class="step <= strength.level ? strengthTone : 'bg-muted'" aria-hidden="true" />
        </div>
        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs" :aria-label="t('adminUsers.passwordCriteria')">
            <span v-for="item in criteria" :key="item.key" class="flex items-center gap-1.5" :class="item.met ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'">
                <Check v-if="item.met" class="size-3.5 shrink-0" aria-hidden="true" />
                <Circle v-else class="size-3.5 shrink-0" aria-hidden="true" />
                {{ t(item.label) }}
            </span>
        </div>
        <p v-if="password && strength.predictable" class="flex items-start gap-1.5 text-xs leading-snug text-amber-700 dark:text-amber-400">
            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            {{ t('adminUsers.passwordPredictable') }}
        </p>
        <p v-else class="text-xs leading-snug text-muted-foreground">{{ t('adminUsers.passwordRecommendation') }}</p>
    </div>
</template>
