<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <section class="space-y-3">
            <Skeleton class="h-4 w-32" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="card in 3" :key="card" class="rounded-2xl border border-border/70 bg-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <Skeleton class="size-9 shrink-0 rounded-lg" />
                            <div class="flex flex-col gap-2">
                                <Skeleton class="h-4 w-32" />
                                <Skeleton class="h-3 w-40" />
                            </div>
                        </div>
                        <Skeleton class="h-6 w-10 rounded-full" />
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <Skeleton class="h-4 w-32" />

            <div class="flex flex-wrap items-center gap-3">
                <Skeleton class="h-10 w-full max-w-xs rounded-lg" />
                <Skeleton class="h-10 w-full rounded-lg sm:w-44" />
                <Skeleton class="h-9 w-24 rounded-lg sm:ml-auto" />
                <Skeleton class="h-9 w-32 rounded-lg" />
            </div>

            <div class="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="card in 6" :key="card" class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-5">
                    <Skeleton class="aspect-video w-full rounded-xl" />
                    <div class="flex items-start justify-between gap-3">
                        <Skeleton class="h-5 w-2/3" />
                        <Skeleton class="h-5 w-16 shrink-0 rounded-full" />
                    </div>
                    <Skeleton class="h-3 w-3/4" />
                    <Skeleton class="h-3 w-1/2" />
                    <div class="mt-auto flex items-center gap-2 border-t border-border/60 pt-3">
                        <Skeleton class="size-7 rounded-full" />
                        <Skeleton class="h-3 w-24" />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
