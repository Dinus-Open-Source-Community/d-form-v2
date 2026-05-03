<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground flex flex-col not-hover:shadow-[6px_6px_0_var(--brutal-ink)] gap-6 rounded-2xl border-2 border-foreground py-6 transition-all duration-200 hover:translate-x-0.5 hover:translate-y-0.5 ',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
