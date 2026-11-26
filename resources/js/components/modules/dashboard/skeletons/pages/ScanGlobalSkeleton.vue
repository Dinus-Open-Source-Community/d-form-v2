<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex flex-col gap-5">
        <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-end justify-end gap-3">
                <Skeleton class="h-10 w-44 rounded-lg" />
                <Skeleton class="h-10 w-52 rounded-lg" />
                <Skeleton class="h-10 w-28 rounded-lg" />
            </div>
            <div class="grid gap-2 sm:grid-cols-3">
                <div
                    v-for="stat in 3"
                    :key="stat"
                    class="border-border bg-card flex flex-col gap-2 rounded-xl border p-4"
                >
                    <Skeleton class="h-3 w-24" />
                    <Skeleton class="h-7 w-20" />
                </div>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="border-border/70 bg-card overflow-hidden rounded-2xl border">
                <div class="bg-muted/20 border-border/70 flex flex-col gap-2 border-b p-4">
                    <Skeleton class="h-5 w-40" />
                    <Skeleton class="h-3 w-56" />
                </div>
                <div class="space-y-4 p-4 md:p-5">
                    <Skeleton class="aspect-square w-full rounded-xl" />
                    <div class="flex flex-wrap items-center gap-3">
                        <Skeleton class="h-10 flex-1 rounded-lg" />
                        <Skeleton class="h-10 w-28 rounded-lg" />
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-5">
                <div v-for="card in 3" :key="card" class="border-border/70 bg-card rounded-2xl border">
                    <div class="border-border/70 border-b p-4">
                        <Skeleton class="h-4 w-32" />
                    </div>
                    <div class="flex flex-col gap-3 p-4">
                        <div v-for="row in 3" :key="row" class="flex items-center gap-3">
                            <Skeleton class="size-8 shrink-0 rounded-full" />
                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <Skeleton class="h-3 w-2/3" />
                                <Skeleton class="h-3 w-1/3" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
