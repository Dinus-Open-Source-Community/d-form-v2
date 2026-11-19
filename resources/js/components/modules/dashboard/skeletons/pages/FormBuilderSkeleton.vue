<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex h-full min-h-0 min-w-0 flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-3">
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                <div class="flex min-h-10 flex-wrap gap-1 rounded-xl bg-muted/40 p-1">
                    <Skeleton class="h-8 w-24 rounded-lg" />
                    <Skeleton class="h-8 w-28 rounded-lg" />
                    <Skeleton class="h-8 w-32 rounded-lg" />
                </div>
                <Skeleton class="h-4 w-24" />
            </div>
            <div class="ml-auto flex shrink-0 items-center gap-2">
                <Skeleton class="h-9 w-24 rounded-lg" />
                <Skeleton class="h-9 w-28 rounded-lg" />
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row lg:gap-4">
            <aside class="hidden w-72 shrink-0 flex-col gap-4 rounded-xl border border-border bg-card p-4 lg:flex">
                <Skeleton class="h-10 w-full rounded-lg" />
                <div class="flex flex-col gap-3">
                    <Skeleton v-for="item in 4" :key="item" class="h-12 w-full rounded-lg" />
                </div>
                <div class="mt-2 flex flex-col gap-3">
                    <Skeleton v-for="item in 4" :key="item" class="h-12 w-full rounded-lg" />
                </div>
            </aside>

            <main class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-hidden">
                <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 py-6">
                    <div class="rounded-2xl border border-border bg-card p-6">
                        <Skeleton class="h-7 w-2/3" />
                        <Skeleton class="mt-3 h-4 w-full" />
                        <Skeleton class="mt-2 h-4 w-5/6" />
                    </div>
                    <div v-for="field in 3" :key="field" class="rounded-2xl border border-border bg-card p-5">
                        <Skeleton class="h-4 w-40" />
                        <Skeleton class="mt-3 h-10 w-full rounded-lg" />
                    </div>
                    <Skeleton class="h-32 w-full rounded-2xl" />
                </div>
            </main>

            <aside class="hidden w-80 shrink-0 flex-col gap-4 rounded-xl border border-border bg-card p-4 xl:flex">
                <Skeleton class="h-5 w-32" />
                <Skeleton class="h-10 w-full rounded-lg" />
                <Skeleton class="h-10 w-full rounded-lg" />
                <Skeleton class="h-24 w-full rounded-lg" />
                <Skeleton class="h-10 w-full rounded-lg" />
            </aside>
        </div>
    </div>
</template>
