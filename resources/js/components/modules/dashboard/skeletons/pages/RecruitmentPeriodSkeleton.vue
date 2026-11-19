<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 pb-8 sm:pb-10">
        <div class="border-border/70 bg-card overflow-hidden rounded-xl border shadow-sm">
            <div
                class="flex flex-col gap-3 px-4 py-4 sm:px-6 sm:py-5 lg:flex-row lg:items-start lg:justify-between lg:gap-6"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Skeleton class="h-6 w-56" />
                        <Skeleton class="h-5 w-16 rounded-full" />
                    </div>
                    <Skeleton class="h-3 w-64" />
                </div>
                <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                    <Skeleton class="h-9 w-24 rounded-lg" />
                    <Skeleton class="h-9 w-32 rounded-lg" />
                    <Skeleton class="h-9 w-28 rounded-lg" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="bg-muted/40 flex min-h-10 flex-wrap gap-1 rounded-xl p-1">
                <Skeleton v-for="tab in 4" :key="tab" class="h-8 w-28 rounded-lg" />
            </div>
            <Skeleton class="ml-auto h-9 w-32 rounded-lg" />
        </div>

        <div class="border-border/70 bg-card rounded-xl border">
            <div class="border-border/60 flex flex-wrap items-center gap-3 border-b p-4">
                <Skeleton class="h-10 w-full max-w-xs rounded-lg" />
                <Skeleton class="h-10 w-40 rounded-lg" />
                <Skeleton class="ml-auto h-9 w-28 rounded-lg" />
            </div>
            <div class="divide-border/60 divide-y">
                <div v-for="row in 6" :key="row" class="flex items-center gap-3 px-4 py-4">
                    <Skeleton class="size-9 shrink-0 rounded-full" />
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <Skeleton class="h-3 w-48 max-w-[60%]" />
                        <Skeleton class="h-3 w-32 max-w-[40%]" />
                    </div>
                    <Skeleton class="hidden h-5 w-20 rounded-full sm:block" />
                    <Skeleton class="h-9 w-20 rounded-lg" />
                </div>
            </div>
        </div>
    </div>
</template>
