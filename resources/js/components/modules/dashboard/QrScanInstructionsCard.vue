<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
</script>

<template>
    <Card class="rounded-2xl border border-border/70 bg-muted/15">
        <CardContent class="grid gap-3 p-4 text-sm md:grid-cols-3 md:p-5">
            <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Langkah 1</p>
                <p class="mt-1 font-medium text-foreground">Pilih kamera lalu klik tombol Mulai kamera.</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Langkah 2</p>
                <p class="mt-1 font-medium text-foreground">Arahkan QR peserta ke kotak scanner sampai terbaca.</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Langkah 3</p>
                <p class="mt-1 font-medium text-foreground">Lihat hasil scan dan toast notifikasi di kanan atas.</p>
            </div>
        </CardContent>
    </Card>
</template>
