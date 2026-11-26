<script setup lang="ts">
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

defineProps<{
    description: string | null;
}>();
</script>

<template>
    <Card class="border-border/70 rounded-2xl shadow-xs">
        <CardHeader class="border-border/60 border-b px-5 pt-4 pb-3">
            <h2 class="font-display text-base font-bold tracking-tight">Tentang acara</h2>
        </CardHeader>
        <CardContent class="px-5 py-4">
            <div class="member-event-description">
                <TiptapRichHtml :html="description" />
            </div>
        </CardContent>
    </Card>
</template>
