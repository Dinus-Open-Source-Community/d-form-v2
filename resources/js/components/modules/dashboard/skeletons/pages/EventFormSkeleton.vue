<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <Skeleton class="h-8 w-48" />
                <Skeleton class="mt-2 h-4 w-64" />
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2 sm:gap-3">
                <SkeletonButton class="w-40" />
                <SkeletonButton class="w-32" />
            </div>
        </div>

        <div class="mb-5 grid gap-5 lg:grid-cols-12 lg:items-stretch">
            <div class="flex flex-col gap-6 lg:col-span-7">
                <div class="border-border/60 bg-card flex h-full flex-col rounded-xl border p-5">
                    <div class="mb-5 flex items-center gap-2.5">
                        <Skeleton class="size-5 shrink-0 rounded" />
                        <Skeleton class="h-5 w-40" />
                    </div>
                    <div class="flex flex-col gap-6">
                        <div v-for="field in 3" :key="field" class="flex flex-col gap-2">
                            <Skeleton class="h-3 w-28" />
                            <Skeleton class="h-10 w-full rounded-md" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Skeleton class="h-3 w-28" />
                            <Skeleton class="h-14 w-full rounded-md" />
                            <Skeleton class="h-32 w-full rounded-md" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-8 lg:col-span-5">
                <div
                    v-for="card in 3"
                    :key="card"
                    class="border-border/60 bg-card rounded-xl border p-5 shadow-xs sm:p-6"
                >
                    <div class="mb-5 flex items-center gap-2.5">
                        <Skeleton class="size-5 shrink-0 rounded" />
                        <Skeleton class="h-5 w-36" />
                    </div>
                    <div class="flex flex-col gap-5">
                        <div v-for="field in card === 1 ? 2 : 3" :key="field" class="flex flex-col gap-2">
                            <Skeleton class="h-3 w-24" />
                            <Skeleton class="h-10 w-full rounded-md" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
