<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto flex w-full flex-col gap-6 pb-6 sm:gap-8">
        <section class="border-border/70 bg-card overflow-hidden rounded-2xl border">
            <div class="flex flex-col lg:grid lg:min-h-[min(26rem,70vh)] lg:grid-cols-2 lg:items-stretch">
                <div
                    class="border-border/60 order-2 flex flex-col justify-center gap-5 border-t px-5 py-7 sm:gap-6 sm:px-8 sm:py-9 lg:order-none lg:border-t-0 lg:border-r lg:px-10"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Skeleton class="h-5 w-16 rounded-full" />
                        <Skeleton class="h-5 w-20 rounded-full" />
                        <Skeleton class="h-5 w-24 rounded-full" />
                    </div>
                    <div class="flex max-w-xl flex-col gap-4">
                        <Skeleton class="h-9 w-full" />
                        <Skeleton class="h-9 w-3/4" />
                        <div class="mt-2 flex flex-col gap-3">
                            <Skeleton class="h-4 w-56" />
                            <Skeleton class="h-4 w-44" />
                        </div>
                    </div>
                    <Skeleton class="mt-2 h-11 w-48 rounded-lg" />
                </div>
                <div class="bg-muted/40 order-1 aspect-video w-full lg:order-none lg:aspect-auto lg:h-full">
                    <Skeleton class="size-full rounded-none" />
                </div>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="border-border bg-card flex flex-col gap-4 rounded-2xl border p-5 lg:col-span-2">
                <Skeleton class="h-5 w-32" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-11/12" />
                <Skeleton class="h-4 w-2/3" />
            </div>
            <div class="border-border bg-card flex flex-col gap-3 rounded-2xl border p-5">
                <Skeleton class="h-5 w-28" />
                <Skeleton class="h-3 w-full" />
                <Skeleton class="h-3 w-5/6" />
                <Skeleton class="mt-2 h-10 w-full rounded-lg" />
            </div>
        </div>
    </div>
</template>
