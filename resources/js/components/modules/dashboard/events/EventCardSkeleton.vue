<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-card">
        <Skeleton class="aspect-video w-full rounded-none" />
        <div class="flex flex-col gap-3 p-4">
            <Skeleton class="h-5 w-3/4" />
            <Skeleton class="h-3 w-1/2" />
            <div class="flex flex-wrap gap-2">
                <Skeleton class="h-5 w-16 rounded-full" />
                <Skeleton class="h-5 w-14 rounded-full" />
            </div>
            <div class="flex items-center justify-between">
                <Skeleton class="h-3 w-24" />
                <Skeleton class="h-8 w-20 rounded-lg" />
            </div>
        </div>
    </div>
</template>
