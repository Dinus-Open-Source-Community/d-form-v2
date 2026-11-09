<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
</script>

<template>
    <div class="app-surface-soft flex flex-col items-center justify-center rounded-2xl py-24 text-center">
        <div class="border-border bg-card grid size-16 place-items-center rounded-full border shadow-xs">
            <Inbox class="text-muted-foreground size-7" />
        </div>
        <h3 class="font-display text-foreground mt-6 text-xl font-bold tracking-[-0.02em]">Belum ada pengiriman</h3>
        <p class="text-muted-foreground mt-2 max-w-md text-sm leading-relaxed">
            Saat ada yang mengisi dan mengirim formulir ini, daftar jawaban akan muncul di sini. Anda bisa meninjau
            status, membuka detail, dan memutuskan diterima atau ditolak jika formulir memakai review.
        </p>
    </div>
</template>
