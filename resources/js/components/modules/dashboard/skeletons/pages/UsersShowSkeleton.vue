<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-5 pb-10">
        <section class="border-border bg-card rounded-2xl border shadow-xs">
            <div class="flex flex-col gap-5 p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <Skeleton class="size-14 shrink-0 rounded-full" />
                        <div class="flex min-w-0 flex-col gap-2">
                            <Skeleton class="h-6 w-52" />
                            <Skeleton class="h-4 w-64" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Skeleton class="h-9 w-28 rounded-lg" />
                        <Skeleton class="h-9 w-24 rounded-lg" />
                    </div>
                </div>
                <dl class="border-border/70 grid gap-x-6 gap-y-3 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in 4" :key="item" class="flex flex-col gap-2">
                        <Skeleton class="h-3 w-20" />
                        <Skeleton class="h-4 w-32" />
                    </div>
                </dl>
            </div>
        </section>

        <section class="bg-border/70 border-border overflow-hidden rounded-2xl border shadow-xs">
            <dl class="grid grid-cols-2 gap-px lg:grid-cols-4">
                <div v-for="stat in 4" :key="stat" class="bg-card flex flex-col gap-2 p-4 sm:p-5">
                    <Skeleton class="h-3 w-24" />
                    <Skeleton class="h-7 w-16" />
                </div>
            </dl>
        </section>

        <section v-for="list in 2" :key="list" class="border-border bg-card rounded-2xl border shadow-xs">
            <div class="border-border/70 flex flex-wrap items-baseline justify-between gap-2 border-b px-5 py-3.5">
                <Skeleton class="h-4 w-40" />
                <Skeleton class="h-3 w-16" />
            </div>
            <div class="divide-border/70 divide-y">
                <div v-for="row in 3" :key="row" class="flex items-center gap-3 px-5 py-4">
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <Skeleton class="h-4 w-2/3 max-w-72" />
                        <Skeleton class="h-3 w-1/2 max-w-56" />
                    </div>
                    <Skeleton class="h-5 w-16 shrink-0 rounded-full" />
                </div>
            </div>
        </section>

        <section class="border-border bg-card rounded-2xl border shadow-xs">
            <div class="border-border/70 border-b px-5 py-3.5">
                <Skeleton class="h-4 w-36" />
            </div>
            <div class="divide-border/70 divide-y">
                <div v-for="row in 4" :key="row" class="flex items-center gap-3 px-5 py-3.5">
                    <Skeleton class="size-8 shrink-0 rounded-full" />
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <Skeleton class="h-3 w-1/2 max-w-80" />
                        <Skeleton class="h-3 w-24" />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
