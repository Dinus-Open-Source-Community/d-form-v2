<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto max-w-2xl px-2">
        <div class="mb-6 flex flex-col gap-3">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-8 w-64" />
            <Skeleton class="h-4 w-48" />
        </div>

        <div class="border-border bg-card mb-6 rounded-2xl border p-5 shadow-xs">
            <Skeleton class="h-4 w-40" />
            <Skeleton class="mt-3 h-3 w-full" />
            <Skeleton class="mt-2 h-3 w-2/3" />
        </div>

        <div class="flex flex-col gap-5">
            <div v-for="field in 4" :key="field" class="flex flex-col gap-2">
                <Skeleton class="h-3 w-28" />
                <Skeleton class="h-11 w-full rounded-lg" />
                <Skeleton class="h-3 w-48" />
            </div>
            <div class="flex justify-end">
                <SkeletonButton class="w-40" />
            </div>
        </div>
    </div>
</template>
