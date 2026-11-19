<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <Skeleton class="h-10 w-36 rounded-lg" />
                <Skeleton class="h-10 w-32 rounded-lg" />
                <Skeleton class="h-10 w-28 rounded-lg" />
            </div>
            <Skeleton class="h-10 w-24 rounded-lg" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="card in 8" :key="card" class="border-border bg-card overflow-hidden rounded-xl border">
                <Skeleton class="h-32 w-full rounded-none" />
                <div class="flex flex-col gap-3 p-4">
                    <Skeleton class="h-5 w-3/4" />
                    <Skeleton class="h-3 w-1/2" />
                    <div class="flex gap-2">
                        <Skeleton class="h-5 w-14 rounded-full" />
                        <Skeleton class="h-5 w-12 rounded-full" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-10 w-48 rounded-lg" />
        </div>
    </div>
</template>
