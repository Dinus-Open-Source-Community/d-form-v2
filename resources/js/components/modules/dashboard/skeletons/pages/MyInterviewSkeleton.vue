<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8">
        <div class="grid items-start gap-6 lg:grid-cols-12">
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-7">
                <div class="border-border/70 bg-card rounded-2xl border p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <Skeleton class="size-14 shrink-0 rounded-full" />
                            <div class="flex min-w-0 flex-col gap-2">
                                <Skeleton class="h-6 w-52" />
                                <Skeleton class="h-4 w-40" />
                            </div>
                        </div>
                        <Skeleton class="h-6 w-20 shrink-0 rounded-full" />
                    </div>
                    <div class="border-border/60 mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2">
                        <div v-for="fact in 4" :key="fact" class="flex flex-col gap-2">
                            <Skeleton class="h-3 w-20" />
                            <Skeleton class="h-4 w-32" />
                        </div>
                    </div>
                </div>

                <div class="border-border/70 bg-card rounded-2xl border">
                    <div class="border-border/70 border-b p-5">
                        <Skeleton class="h-5 w-40" />
                    </div>
                    <div class="flex flex-col gap-4 p-5">
                        <div v-for="row in 3" :key="row" class="flex flex-col gap-2">
                            <Skeleton class="h-3 w-32" />
                            <Skeleton class="h-10 w-full rounded-md" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-border/70 bg-card rounded-2xl border lg:col-span-5">
                <div class="flex flex-col gap-4 p-6">
                    <Skeleton class="h-5 w-36" />
                    <div v-for="row in 4" :key="row" class="flex items-center justify-between gap-3">
                        <Skeleton class="h-3 w-28" />
                        <Skeleton class="h-4 w-20" />
                    </div>
                    <Skeleton class="mt-2 h-10 w-full rounded-lg" />
                </div>
            </div>
        </div>
    </div>
</template>
