<script setup lang="ts">
interface IPageHeaderProps {
    title: string;
    subtitle?: string;
}

defineProps<IPageHeaderProps>();
</script>

<template>
    <div
        class="border-border/70 bg-card/70 dark:bg-card/80 flex flex-col gap-4 rounded-2xl border p-4 shadow-sm ring-1 ring-black/[0.03] backdrop-blur-sm sm:p-5 md:flex-row md:flex-wrap md:items-start md:justify-between md:gap-6 dark:ring-white/[0.06]"
    >
        <div class="min-w-0 flex-1 space-y-1.5">
            <h1
                class="font-display text-[1.35rem] leading-tight font-bold tracking-tight text-balance sm:text-2xl lg:text-[1.875rem]"
            >
                {{ title }}
            </h1>
            <p v-if="subtitle" class="text-muted-foreground max-w-2xl text-[0.9375rem] leading-relaxed">
                {{ subtitle }}
            </p>
        </div>
        <div class="w-full min-w-0 md:w-auto md:shrink-0 md:pt-0.5">
            <slot name="actions" />
        </div>
    </div>
</template>
