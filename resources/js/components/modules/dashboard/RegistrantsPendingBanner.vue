<script setup lang="ts">
import { Clock } from 'lucide-vue-next';

defineProps<{
    pendingCount: number;
    activeStatusTab: 'all' | 'pending' | 'accepted' | 'rejected';
}>();
</script>

<template>
    <div
        v-if="pendingCount > 0 && activeStatusTab !== 'pending'"
        class="border-warning/30 bg-warning/5 flex items-start gap-3 rounded-2xl border px-4 py-4 shadow-sm"
        role="status"
    >
        <div
            class="border-warning/25 bg-warning/10 text-warning-foreground mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full border"
        >
            <Clock class="size-4" aria-hidden="true" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-foreground text-sm font-medium">
                {{ pendingCount === 1 ? 'Satu pendaftar menunggu' : `${pendingCount} pendaftar menunggu` }} keputusan
                review.
            </p>
            <p class="text-muted-foreground mt-1 text-sm leading-relaxed">
                Gunakan tab <span class="text-foreground font-medium">Menunggu</span> di bagian filter untuk hanya
                menampilkan entri tersebut.
            </p>
        </div>
    </div>
</template>
