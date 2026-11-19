<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex flex-col gap-6 md:gap-8">
        <section class="space-y-4">
            <Skeleton class="h-5 w-16" />
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="card in 4" :key="card" class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4">
                    <Skeleton class="h-3 w-24" />
                    <Skeleton class="h-7 w-20" />
                    <Skeleton class="h-3 w-16" />
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <Skeleton class="h-5 w-40" />
                    <Skeleton class="mt-2 h-3 w-52" />
                </div>
                <Skeleton class="h-8 w-24 rounded-lg" />
            </div>
            <div class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4">
                <div v-for="row in 5" :key="row" class="flex items-center gap-4">
                    <Skeleton class="size-10 shrink-0 rounded-lg" />
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <Skeleton class="h-4 w-2/3" />
                        <Skeleton class="h-3 w-1/3" />
                    </div>
                    <Skeleton class="h-6 w-16 rounded-full" />
                </div>
            </div>
        </section>
    </div>
</template>
