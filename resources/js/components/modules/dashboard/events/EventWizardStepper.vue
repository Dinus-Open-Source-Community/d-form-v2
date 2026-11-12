<script setup lang="ts">
import { Check } from 'lucide-vue-next';

interface Step {
    key: string;
    label: string;
}

defineProps<{
    steps: Step[];
    activeIndex: number;
}>();
</script>

<template>
    <nav aria-label="Tahapan pembuatan acara" class="flex min-w-0 items-center">
        <template v-for="(step, i) in steps" :key="step.key">
            <span
                v-if="i > 0"
                aria-hidden="true"
                class="bg-border mx-2.5 h-px w-6 shrink-0 transition-colors duration-500 sm:mx-3 sm:w-8"
                :class="i <= activeIndex ? 'bg-success/50' : ''"
            />
            <span
                class="flex min-w-0 items-center gap-2 transition-colors duration-300"
                :class="i === activeIndex ? 'text-foreground' : 'text-muted-foreground'"
            >
                <span class="grid w-4 shrink-0 place-items-center">
                    <Check
                        v-if="i < activeIndex"
                        class="text-success size-3.5"
                        :stroke-width="2.75"
                        aria-hidden="true"
                    />
                    <span
                        v-else-if="i === activeIndex"
                        class="bg-primary ring-primary/10 size-2 rounded-full ring-4"
                        aria-hidden="true"
                    />
                    <span v-else class="bg-muted-foreground/30 size-1.5 rounded-full" aria-hidden="true" />
                </span>
                <span
                    class="truncate text-[13px] leading-none tracking-[-0.01em]"
                    :class="i === activeIndex ? 'font-semibold' : 'font-medium'"
                    :aria-current="i === activeIndex ? 'step' : undefined"
                >
                    {{ step.label }}
                </span>
            </span>
        </template>
    </nav>
</template>
