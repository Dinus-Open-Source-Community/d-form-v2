<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8">
        <div class="border-border/70 bg-background rounded-2xl border p-3">
            <div class="flex flex-col gap-2.5">
                <Skeleton class="h-10 w-full rounded-lg" />
                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                    <div
                        v-for="stat in 4"
                        :key="stat"
                        class="border-border/60 bg-card flex flex-col gap-2 rounded-xl border p-3"
                    >
                        <Skeleton class="h-3 w-20" />
                        <Skeleton class="h-6 w-12" />
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Skeleton class="h-3 w-56" />
                    <Skeleton class="h-8 w-24 rounded-lg" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <Skeleton v-for="pill in 5" :key="pill" class="h-9 w-28 rounded-full" />
        </div>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
            <section v-for="section in 2" :key="section" class="flex min-w-0 flex-col gap-3">
                <div class="flex items-baseline justify-between gap-3">
                    <Skeleton class="h-5 w-40" />
                    <Skeleton class="h-3 w-16" />
                </div>
                <div class="grid gap-3">
                    <div v-for="card in 3" :key="card" class="border-border/70 bg-card rounded-2xl border p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 flex-col gap-2">
                                <Skeleton class="h-4 w-40" />
                                <Skeleton class="h-3 w-28" />
                            </div>
                            <Skeleton class="h-6 w-16 shrink-0 rounded-full" />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid gap-3">
            <div v-for="card in 3" :key="card" class="border-border/70 bg-card rounded-2xl border p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-2">
                        <Skeleton class="h-4 w-48" />
                        <Skeleton class="h-3 w-32" />
                    </div>
                    <Skeleton class="h-6 w-20 shrink-0 rounded-full" />
                </div>
            </div>
        </div>
    </div>
</template>
