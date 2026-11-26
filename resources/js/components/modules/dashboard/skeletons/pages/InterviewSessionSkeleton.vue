<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <SkeletonButton class="w-36" />
        </div>

        <div class="border-border/70 bg-card rounded-2xl border">
            <div class="border-border/70 border-b p-5 pb-2">
                <Skeleton class="h-5 w-40" />
            </div>
            <div class="grid gap-2 p-5 text-sm sm:grid-cols-2">
                <div v-for="row in 6" :key="row" class="flex flex-col gap-2">
                    <Skeleton class="h-3 w-24" />
                    <Skeleton class="h-4 w-40" />
                </div>
            </div>
        </div>

        <div class="border-border/70 bg-card rounded-2xl border">
            <div class="border-border/70 border-b p-5 pb-2">
                <Skeleton class="h-5 w-56" />
            </div>
            <div class="p-5">
                <div class="divide-border/70 divide-y rounded-lg border">
                    <div v-for="row in 4" :key="row" class="flex items-center gap-3 p-3">
                        <Skeleton class="size-9 shrink-0 rounded-full" />
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Skeleton class="h-3 w-40" />
                            <Skeleton class="h-3 w-24" />
                        </div>
                        <Skeleton class="h-6 w-16 shrink-0 rounded-full" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
