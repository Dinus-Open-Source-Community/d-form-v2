<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="card in 3" :key="card" class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4">
                <Skeleton class="h-3 w-24" />
                <Skeleton class="h-7 w-16" />
                <Skeleton class="h-3 w-20" />
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card">
            <div class="flex flex-row items-center justify-between border-b border-border p-4">
                <Skeleton class="h-5 w-40" />
                <Skeleton class="h-7 w-20 rounded-lg" />
            </div>
            <div class="flex flex-col gap-3 p-4">
                <div v-for="row in 3" :key="row" class="flex items-center gap-4 rounded-lg border border-border p-3">
                    <Skeleton class="h-14 w-20 shrink-0 rounded-md" />
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <Skeleton class="h-4 w-2/3" />
                        <Skeleton class="h-3 w-1/2" />
                    </div>
                    <Skeleton class="h-5 w-16 rounded-full" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4">
            <div class="flex items-center justify-between">
                <Skeleton class="h-5 w-32" />
                <Skeleton class="h-8 w-24 rounded-lg" />
            </div>
            <div class="mt-4 grid grid-cols-7 gap-1.5">
                <Skeleton v-for="day in 28" :key="day" class="h-12 w-full rounded-md" />
            </div>
        </div>
    </div>
</template>
