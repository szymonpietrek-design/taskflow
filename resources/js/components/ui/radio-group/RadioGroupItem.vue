<script setup lang="ts">
import type { RadioGroupItemProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { Circle } from '@lucide/vue'
import { RadioGroupIndicator, RadioGroupItem, useForwardProps } from 'reka-ui'
import { reactiveOmit } from '@vueuse/core'
import { cn } from '@/lib/utils'

const props = defineProps<RadioGroupItemProps & { class?: HTMLAttributes['class'] }>()
const forwarded = useForwardProps(reactiveOmit(props, 'class'))
</script>

<template>
    <RadioGroupItem
        data-slot="radio-group-item"
        v-bind="forwarded"
        :class="cn('border-input text-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 aria-invalid:border-destructive size-4 shrink-0 rounded-full border shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50', props.class)"
    >
        <RadioGroupIndicator class="relative flex items-center justify-center">
            <Circle class="size-2 fill-primary" />
        </RadioGroupIndicator>
    </RadioGroupItem>
</template>
