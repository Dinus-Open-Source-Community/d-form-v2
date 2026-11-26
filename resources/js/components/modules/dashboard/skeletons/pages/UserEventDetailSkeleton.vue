<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 pb-6" aria-hidden="true">
        <Skeleton class="h-44 w-full rounded-2xl sm:h-60" />

        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <Skeleton class="h-8 w-2/3 max-w-md" />
                <Skeleton class="h-5 w-36 rounded-full" />
            </div>
            <Skeleton class="h-4 w-1/2 max-w-sm" />
        </div>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div
                v-for="cell in 4"
                :key="cell"
                class="border-border bg-card flex min-h-[76px] items-start gap-2.5 rounded-xl border p-4"
            >
                <Skeleton class="mt-0.5 size-4 shrink-0 rounded-sm" />
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                    <Skeleton class="h-3 w-16" />
                    <Skeleton class="h-4 w-24" />
                </div>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
            <div class="flex flex-col gap-5">
                <div class="border-border bg-card rounded-2xl border p-5">
                    <Skeleton class="h-5 w-32" />
                    <Skeleton class="mt-4 h-4 w-full" />
                    <Skeleton class="mt-2 h-4 w-11/12" />
                    <Skeleton class="mt-2 h-4 w-2/3" />
                </div>
                <div class="border-border bg-card rounded-2xl border p-5">
                    <Skeleton class="h-5 w-32" />
                    <Skeleton class="mt-4 h-14 w-full rounded-xl" />
                    <Skeleton class="mt-3 h-14 w-full rounded-xl" />
                </div>
            </div>
            <div class="border-border bg-card rounded-2xl border p-5">
                <Skeleton class="h-5 w-24" />
                <Skeleton class="mt-4 h-4 w-40" />
                <Skeleton class="mt-4 h-48 w-full rounded-xl" />
            </div>
        </div>
    </div>
</template>
