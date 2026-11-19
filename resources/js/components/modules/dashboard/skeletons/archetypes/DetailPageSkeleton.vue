<script setup lang="ts">
import SkeletonStatCard from '@/components/core/skeleton/SkeletonStatCard.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-5 pb-10">
        <div class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-5 sm:flex-row sm:items-center">
            <Skeleton class="size-16 shrink-0 rounded-full" />
            <div class="flex min-w-0 flex-col gap-2">
                <Skeleton class="h-6 w-52" />
                <Skeleton class="h-4 w-40" />
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="flex flex-col gap-5 lg:col-span-2">
                <div v-for="section in 2" :key="section" class="rounded-2xl border border-border bg-card p-5">
                    <Skeleton class="h-5 w-40" />
                    <Skeleton class="mt-4 h-4 w-full" />
                    <Skeleton class="mt-2 h-4 w-11/12" />
                    <Skeleton class="mt-2 h-4 w-3/4" />
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <SkeletonStatCard />
                <SkeletonStatCard />
            </div>
        </div>
    </div>
</template>
