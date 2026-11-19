<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pb-10">
        <div class="min-w-0">
            <Skeleton class="h-8 w-64" />
            <Skeleton class="mt-2 h-4 w-80" />
        </div>

        <div v-for="section in 2" :key="section" class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-5">
            <Skeleton class="h-5 w-36" />
            <div v-for="field in 4" :key="field" class="flex flex-col gap-2">
                <Skeleton class="h-3 w-28" />
                <Skeleton class="h-10 w-full rounded-md" />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <SkeletonButton class="w-24" />
            <SkeletonButton class="w-32" />
        </div>
    </div>
</template>
