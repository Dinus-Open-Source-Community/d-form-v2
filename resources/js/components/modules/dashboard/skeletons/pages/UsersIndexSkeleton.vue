<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <Skeleton class="h-8 w-48" />
                <Skeleton class="mt-2 h-4 w-64" />
            </div>
            <SkeletonButton class="w-full sm:w-32" />
        </div>

        <section class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
                <Skeleton class="h-10 w-full max-w-72 rounded-lg" />
                <Skeleton class="h-10 w-40 rounded-lg" />
                <Skeleton class="ml-auto h-4 w-32" />
            </div>

            <div class="divide-y divide-border/70">
                <div v-for="row in 6" :key="row" class="flex items-center gap-3 px-4 py-4">
                    <Skeleton class="size-10 shrink-0 rounded-full" />
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <Skeleton class="h-3 w-40 max-w-[60%]" />
                        <Skeleton class="h-3 w-56 max-w-[80%]" />
                    </div>
                    <Skeleton class="hidden h-5 w-16 rounded-full lg:block" />
                    <Skeleton class="hidden h-9 w-24 rounded-lg lg:block" />
                </div>
            </div>
        </section>
    </div>
</template>
