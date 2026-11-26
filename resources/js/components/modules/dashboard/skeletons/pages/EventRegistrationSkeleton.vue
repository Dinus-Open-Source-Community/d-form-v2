<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 sm:gap-6">
        <div class="flex flex-wrap items-center gap-2">
            <Skeleton class="h-5 w-24 rounded-full" />
        </div>

        <div class="border-primary/20 bg-primary/5 rounded-xl border shadow-xs">
            <div class="p-4 pb-3">
                <Skeleton class="h-4 w-20" />
            </div>
            <div class="px-4 pb-4">
                <Skeleton class="h-3 w-full" />
                <Skeleton class="mt-2 h-3 w-3/4" />
            </div>
        </div>

        <div class="border-border bg-card overflow-hidden rounded-xl border shadow-sm">
            <Skeleton class="h-48 w-full rounded-none" />
            <div class="border-border border-t px-4 py-4 sm:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 flex-col gap-2">
                        <Skeleton class="h-6 w-56" />
                        <Skeleton class="h-4 w-40" />
                    </div>
                    <Skeleton class="h-6 w-24 rounded-full" />
                </div>
                <div
                    class="border-border/60 mt-4 grid min-w-0 gap-x-6 gap-y-3 border-t pt-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div v-for="fact in 4" :key="fact" class="flex flex-col gap-2">
                        <Skeleton class="h-3 w-20" />
                        <Skeleton class="h-4 w-32" />
                    </div>
                </div>
            </div>
        </div>

        <div class="border-border bg-card rounded-xl border shadow-xs">
            <div class="border-border/60 border-b p-4 pb-3">
                <Skeleton class="h-4 w-28" />
            </div>
            <div class="grid min-w-0 grid-cols-1 gap-3 p-4 pt-0 lg:grid-cols-2">
                <div
                    v-for="answer in 4"
                    :key="answer"
                    class="border-border/60 bg-muted/15 min-w-0 rounded-lg border p-3 sm:p-4"
                >
                    <Skeleton class="h-3 w-24" />
                    <Skeleton class="mt-2 h-4 w-3/4" />
                </div>
            </div>
        </div>

        <div class="border-success/25 bg-success/5 rounded-xl border shadow-xs">
            <div class="p-4 pb-3">
                <Skeleton class="h-4 w-20" />
            </div>
            <div class="flex flex-col items-center gap-4 px-4 pb-4 sm:flex-row sm:items-start">
                <Skeleton class="size-32 shrink-0 rounded-xl" />
                <div class="w-full max-w-sm space-y-2">
                    <Skeleton class="h-4 w-40" />
                    <Skeleton class="h-3 w-full" />
                    <Skeleton class="h-3 w-2/3" />
                </div>
            </div>
        </div>

        <div class="border-border bg-card rounded-xl border shadow-xs">
            <div class="px-4 py-6 text-center">
                <Skeleton class="mx-auto h-4 w-2/3" />
            </div>
        </div>

        <div class="border-border bg-card rounded-xl border shadow-xs">
            <div class="p-4 pb-3">
                <Skeleton class="h-4 w-32" />
            </div>
            <div class="space-y-2 px-4 pb-4">
                <Skeleton class="h-3 w-full" />
                <Skeleton class="h-3 w-2/3" />
            </div>
        </div>
    </div>
</template>
