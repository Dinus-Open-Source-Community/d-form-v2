<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    id: string;
    label: string;
    description: string;
}>();
</script>

<template>
    <Card class="h-full rounded-2xl transition-colors hover:border-primary/30">
        <CardContent class="p-5">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold">{{ label }}</h3>
                <span class="rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide text-primary">
                    {{ id }}
                </span>
            </div>
            <p class="text-muted-foreground mt-2 text-sm">{{ description }}</p>
        </CardContent>
    </Card>
</template>
