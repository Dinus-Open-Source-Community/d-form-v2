<script setup lang="ts">
import type { SwitchRootEmits, SwitchRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import {
  SwitchRoot,
  SwitchThumb,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SwitchRootProps & { class?: HTMLAttributes["class"] }>()

const emits = defineEmits<SwitchRootEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <SwitchRoot
    v-slot="slotProps"
    data-slot="switch"
    v-bind="forwarded"
    :class="cn(
      'peer inline-flex h-6 w-10 shrink-0 items-center rounded-full border border-border bg-muted shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-ring/30 focus-visible:ring-[3px] data-[state=checked]:border-primary/30 data-[state=checked]:bg-primary disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  >
    <SwitchThumb
      data-slot="switch-thumb"
      :class="cn('pointer-events-none block size-4 translate-x-0.5 rounded-full bg-card shadow-xs ring-0 transition-transform data-[state=checked]:translate-x-[1.125rem] data-[state=checked]:bg-primary-foreground')"
    >
      <slot name="thumb" v-bind="slotProps" />
    </SwitchThumb>
  </SwitchRoot>
</template>
