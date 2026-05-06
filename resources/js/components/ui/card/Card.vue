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
                'flex flex-col gap-6 rounded-2xl border border-border bg-card py-6 text-card-foreground shadow-sm transition-[border-color,transform] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:border-primary/20',
                props.class
            )
        "
    >
        <slot />
    </div>
</template>
