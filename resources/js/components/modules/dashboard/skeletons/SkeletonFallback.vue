<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex min-h-full flex-col gap-6">
        <div class="flex flex-col gap-2">
            <Skeleton class="h-8 w-56" />
            <Skeleton class="h-4 w-80" />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Skeleton class="h-10 w-full max-w-72 rounded-lg" />
            <Skeleton class="h-10 w-28 rounded-lg" />
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div v-for="card in 3" :key="card" class="rounded-2xl border border-border bg-card p-4">
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="mt-3 h-3 w-full" />
                <Skeleton class="mt-2 h-3 w-5/6" />
            </div>
        </div>
    </div>
</template>
