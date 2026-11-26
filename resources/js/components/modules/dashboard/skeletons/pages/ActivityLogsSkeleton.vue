<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8">
        <div class="border-border/70 bg-card rounded-2xl border">
            <div class="flex flex-wrap gap-3 p-4">
                <Skeleton class="h-9 w-40 rounded-md" />
                <Skeleton class="h-9 min-w-[200px] flex-1 rounded-md" />
            </div>
        </div>

        <div class="border-border/70 bg-card rounded-2xl border">
            <div class="divide-border/70 divide-y">
                <div v-for="row in 6" :key="row" class="space-y-2 p-4">
                    <Skeleton class="h-4 w-1/2 max-w-96" />
                    <Skeleton class="h-3 w-full max-w-lg" />
                </div>
            </div>
        </div>
    </div>
</template>
