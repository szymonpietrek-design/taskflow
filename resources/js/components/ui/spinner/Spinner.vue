<script setup lang="ts">
import { useI18n } from "@/composables/useI18n"
import type { HTMLAttributes } from "vue"
import { Loader2Icon } from "@lucide/vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
const { t } = useI18n()
</script>

<template>
  <Loader2Icon
    role="status"
    :aria-label="t('ui.loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
