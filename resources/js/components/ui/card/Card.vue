<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        data-slot="card"
        :class="
            cn(
                'bg-card text-card-foreground border-foreground flex flex-col gap-6 rounded-2xl border-2 py-6 transition-all duration-300 not-hover:shadow-[6px_6px_0_var(--brutal-ink)] hover:translate-x-0.5 hover:translate-y-0.5',
                props.class
            )
        "
    >
        <slot />
    </div>
</template>
