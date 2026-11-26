<script setup lang="ts">
import SkeletonButton from '@/components/core/skeleton/SkeletonButton.vue';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 sm:py-8">
        <section class="flex flex-col items-center text-center">
            <Skeleton class="mb-8 aspect-[4/3] w-full max-w-md rounded-2xl" />

            <div class="flex w-full max-w-xl flex-col items-center gap-3">
                <Skeleton class="h-9 w-72" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-2/3" />
            </div>

            <div class="border-border bg-muted/20 mt-8 w-full max-w-2xl rounded-2xl border p-5">
                <Skeleton class="h-4 w-full" />
                <Skeleton class="mt-2 h-4 w-11/12" />
                <Skeleton class="mt-2 h-4 w-2/3" />
            </div>

            <div class="mt-8 flex w-full max-w-2xl flex-col gap-3 sm:flex-row sm:flex-wrap">
                <SkeletonButton class="w-full sm:w-40" />
                <SkeletonButton class="w-full sm:w-40" />
            </div>
        </section>
    </div>
</template>
