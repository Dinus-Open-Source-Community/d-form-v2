<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pb-10">
        <div class="min-w-0">
            <Skeleton class="h-8 w-56" />
            <Skeleton class="mt-2 h-4 w-72" />
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="border-border bg-card rounded-2xl border p-5 lg:col-span-2">
                <Skeleton class="h-5 w-40" />
                <Skeleton class="mt-4 h-4 w-full" />
                <Skeleton class="mt-2 h-4 w-11/12" />
                <Skeleton class="mt-2 h-4 w-2/3" />
                <Skeleton class="mt-6 h-28 w-full rounded-xl" />
            </div>
            <div class="border-border bg-card rounded-2xl border p-5">
                <Skeleton class="h-5 w-32" />
                <Skeleton class="mt-4 h-3 w-full" />
                <Skeleton class="mt-2 h-3 w-5/6" />
                <Skeleton class="mt-2 h-3 w-4/6" />
            </div>
        </div>
    </div>
</template>
