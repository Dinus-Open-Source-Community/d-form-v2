<script setup lang="ts">
import { Inbox } from 'lucide-vue-next'
</script>

<template>
    <div class="app-surface-soft flex flex-col items-center justify-center py-24 text-center">
        <div class="grid size-16 place-items-center rounded-2xl border border-border bg-card shadow-xs">
            <Inbox class="size-7 text-muted-foreground" />
        </div>
        <h3 class="font-display mt-6 text-xl font-bold tracking-[-0.02em] text-foreground">No responses yet</h3>
        <p class="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
            When people submit this form, you'll see their detailed answers here in real-time.
        </p>
    </div>
</template>
