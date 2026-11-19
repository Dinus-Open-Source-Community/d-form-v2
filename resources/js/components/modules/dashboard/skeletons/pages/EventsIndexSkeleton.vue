<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <Skeleton class="h-9 w-36 rounded-lg" />
                <Skeleton class="h-9 w-32 rounded-lg" />
                <Skeleton class="h-9 w-28 rounded-lg" />
            </div>
            <SkeletonButton class="w-full sm:w-32" />
        </div>

        <div class="grid min-w-0 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3 2xl:grid-cols-4">
            <div v-for="card in 8" :key="card" class="border-border bg-card overflow-hidden rounded-xl border">
                <Skeleton class="h-36 w-full rounded-none" />
                <div class="flex flex-col gap-3 p-4">
                    <Skeleton class="h-5 w-3/4" />
                    <Skeleton class="h-3 w-1/2" />
                    <div class="flex gap-2">
                        <Skeleton class="h-5 w-16 rounded-full" />
                        <Skeleton class="h-5 w-14 rounded-full" />
                    </div>
                    <div class="flex items-center justify-between">
                        <Skeleton class="h-3 w-24" />
                        <Skeleton class="h-8 w-20 rounded-lg" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3">
            <Skeleton class="h-4 w-40" />
            <Skeleton class="h-10 w-48 rounded-lg" />
        </div>
    </div>
</template>
