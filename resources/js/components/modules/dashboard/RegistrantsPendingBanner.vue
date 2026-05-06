<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Clock, ArrowUpRight } from 'lucide-vue-next'

defineProps<{
    pendingCount: number
    activeStatusTab: 'all' | 'pending' | 'accepted' | 'rejected'
}>()

const emit = defineEmits<{
    reviewPending: []
}>()
</script>

<template>
    <div
        v-if="pendingCount > 0 && activeStatusTab !== 'pending'"
        class="flex items-start gap-3 rounded-2xl border border-warning/20 bg-warning/5 px-4 py-3"
    >
        <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning-foreground">
            <Clock class="size-4" />
        </div>
        <div class="flex-1 text-sm">
            <p class="font-medium text-foreground">
                {{ pendingCount }} {{ pendingCount === 1 ? 'person is' : 'people are' }} waiting for your decision.
            </p>
            <p class="mt-0.5 text-xs text-muted-foreground">
                A quick review helps them plan — most registrants hope for a response within 24 hours.
            </p>
        </div>
        <Button variant="ghost" size="sm" class="rounded-full" @click="emit('reviewPending')">
            Review now
            <ArrowUpRight class="ml-1 size-3.5" />
        </Button>
    </div>
</template>
