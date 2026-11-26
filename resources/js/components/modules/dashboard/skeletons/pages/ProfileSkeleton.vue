<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="mx-auto w-full max-w-5xl">
        <div class="@container mx-auto max-w-[48rem] min-w-0">
            <header class="min-w-0 pb-6 @min-[640px]:pb-8">
                <div
                    class="flex flex-col gap-5 @min-[640px]:flex-row @min-[640px]:items-start @min-[640px]:justify-between"
                >
                    <div class="flex min-w-0 items-start gap-4">
                        <Skeleton class="size-16 shrink-0 rounded-full" />
                        <div class="flex min-w-0 flex-col gap-2">
                            <Skeleton class="h-6 w-48" />
                            <Skeleton class="h-4 w-56" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 @min-[640px]:shrink-0 @min-[640px]:items-end">
                        <Skeleton class="h-9 w-32 rounded-lg" />
                        <Skeleton class="h-3 w-28" />
                    </div>
                </div>
            </header>

            <div class="border-border bg-card min-w-0 rounded-2xl border shadow-xs">
                <section
                    v-for="section in 3"
                    :key="section"
                    class="border-border border-t px-5 py-8 first:border-t-0 @min-[640px]:px-8"
                >
                    <Skeleton class="h-5 w-40" />
                    <Skeleton class="mt-2 h-3 w-64" />

                    <div class="mt-6 min-w-0">
                        <div v-if="section === 1" class="grid gap-5">
                            <div v-for="field in 2" :key="field" class="flex flex-col gap-2">
                                <Skeleton class="h-3 w-24" />
                                <Skeleton class="h-11 w-full rounded-md" />
                            </div>
                        </div>
                        <div v-else-if="section === 2" class="grid gap-6">
                            <div class="flex min-h-[44px] items-center justify-between gap-4">
                                <Skeleton class="h-4 w-24" />
                                <Skeleton class="h-9 w-28 rounded-lg" />
                            </div>
                            <div class="border-border grid gap-5 border-t pt-6">
                                <div v-for="field in 2" :key="field" class="flex flex-col gap-2">
                                    <Skeleton class="h-3 w-28" />
                                    <Skeleton class="h-11 w-full rounded-md" />
                                </div>
                            </div>
                        </div>
                        <div v-else class="divide-border divide-y">
                            <div v-for="row in 2" :key="row" class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <Skeleton class="size-8 shrink-0 rounded-full" />
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <Skeleton class="h-3 w-2/3" />
                                    <Skeleton class="h-3 w-1/3" />
                                </div>
                                <Skeleton class="h-9 w-24 rounded-lg" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="section < 3"
                        class="mt-6 flex flex-col gap-3 @min-[640px]:flex-row @min-[640px]:items-center @min-[640px]:justify-end"
                    >
                        <Skeleton class="h-4 w-32 @min-[640px]:mr-auto" />
                        <Skeleton class="h-11 w-full rounded-lg @min-[640px]:w-32" />
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
