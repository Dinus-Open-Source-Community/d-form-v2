<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex min-w-0 flex-col gap-6 pb-6 sm:gap-8">
        <div class="flex min-w-0 flex-col gap-5 sm:gap-6 md:grid md:grid-cols-5 md:gap-6 xl:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 md:col-span-3 xl:col-span-2">
                <div class="overflow-hidden rounded-xl border border-border bg-card">
                    <Skeleton class="h-48 w-full rounded-none sm:h-64" />
                    <div class="flex flex-col gap-3 p-5">
                        <Skeleton class="h-7 w-3/4" />
                        <div class="flex flex-wrap gap-3">
                            <Skeleton class="h-4 w-32" />
                            <Skeleton class="h-4 w-24" />
                            <Skeleton class="h-4 w-20" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
                    <Skeleton class="h-5 w-32" />
                    <Skeleton class="h-4 w-full" />
                    <Skeleton class="h-4 w-11/12" />
                    <Skeleton class="h-4 w-2/3" />
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-6 md:col-span-2 xl:col-span-1">
                <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
                    <Skeleton class="h-5 w-28" />
                    <div v-for="row in 3" :key="row" class="flex items-center gap-3">
                        <Skeleton class="size-9 shrink-0 rounded-lg" />
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Skeleton class="h-4 w-2/3" />
                            <Skeleton class="h-3 w-1/3" />
                        </div>
                    </div>
                    <Skeleton class="h-10 w-full rounded-lg" />
                </div>

                <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
                    <div class="flex items-center justify-between">
                        <Skeleton class="h-5 w-32" />
                        <Skeleton class="h-6 w-14 rounded-full" />
                    </div>
                    <Skeleton class="h-2 w-full rounded-full" />
                    <div class="grid grid-cols-2 gap-3">
                        <Skeleton class="h-14 w-full rounded-lg" />
                        <Skeleton class="h-14 w-full rounded-lg" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
