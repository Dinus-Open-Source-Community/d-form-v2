<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <Skeleton class="h-8 w-56" />
                <Skeleton class="mt-2 h-4 w-40" />
            </div>
            <SkeletonButton class="w-full shrink-0 sm:w-28" />
        </div>

        <div class="border-border/70 bg-card rounded-2xl border p-6">
            <div class="flex flex-col gap-4">
                <div v-for="field in 5" :key="field" class="flex flex-col gap-2">
                    <Skeleton class="h-3 w-32" />
                    <Skeleton class="h-10 w-full rounded-md" />
                </div>

                <Skeleton class="h-32 w-full rounded-xl" />

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div v-for="group in 2" :key="group" class="flex flex-col gap-4">
                        <div v-for="field in 2" :key="field" class="flex flex-col gap-2">
                            <Skeleton class="h-3 w-28" />
                            <Skeleton class="h-10 w-full rounded-md" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
