<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import SkeletonCard from '@/components/core/skeleton/SkeletonCard.vue';
import SkeletonToolbar from '@/components/core/skeleton/SkeletonToolbar.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <Skeleton class="h-8 w-56" />
                <Skeleton class="mt-2 h-4 w-72" />
            </div>
            <SkeletonButton class="w-full sm:w-32" />
        </div>

        <SkeletonToolbar />

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <SkeletonCard v-for="card in 6" :key="card" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-10 w-40 rounded-lg" />
        </div>
    </div>
</template>
