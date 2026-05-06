<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { CircleHelp, Clock3 } from 'lucide-vue-next'
import { SCAN_STATUS_THEME, type ScanEntry, type ScanResult } from '@/lib/qrScanUi'

defineProps<{
    scanResult: ScanResult | null
    scanHistory: ScanEntry[]
}>()

defineEmits<{
    clearHistory: []
}>()
</script>

<template>
    <div class="flex flex-col gap-5">
        <Card class="rounded-2xl border border-border/70">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-semibold">Hasil Scan Terakhir</CardTitle>
            </CardHeader>
            <CardContent class="pt-0">
                <div v-if="scanResult" :class="['rounded-xl p-3', SCAN_STATUS_THEME[scanResult.status].bg]">
                    <div class="flex items-start gap-3">
                        <component
                            :is="SCAN_STATUS_THEME[scanResult.status].icon"
                            :class="['mt-0.5 size-5', SCAN_STATUS_THEME[scanResult.status].class]"
                        />
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground">{{ scanResult.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ scanResult.email }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <Badge variant="outline" :class="SCAN_STATUS_THEME[scanResult.status].class">
                                    {{ SCAN_STATUS_THEME[scanResult.status].label }}
                                </Badge>
                                <Badge variant="outline">{{ scanResult.source === 'camera' ? 'Kamera' : 'Manual' }}</Badge>
                            </div>
                            <p class="mt-2 truncate text-xs text-muted-foreground">Raw code: {{ scanResult.rawCode }}</p>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-xl border border-dashed border-border/80 bg-muted/20 px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Belum ada scan. Mulai kamera atau gunakan input manual.
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border border-border/70">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between gap-3">
                    <CardTitle class="text-base font-semibold">Riwayat Scan</CardTitle>
                    <Button variant="ghost" size="sm" class="h-8 text-xs" :disabled="scanHistory.length === 0" @click="$emit('clearHistory')">
                        Bersihkan
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="pt-0">
                <div v-if="scanHistory.length > 0" class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                    <div
                        v-for="entry in scanHistory"
                        :key="entry.id"
                        class="rounded-xl border border-border/70 bg-background px-3 py-2.5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-foreground">{{ entry.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ entry.email }}</p>
                            </div>
                            <span class="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                                <Clock3 class="size-3.5" />
                                {{ entry.time }}
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge variant="outline" :class="SCAN_STATUS_THEME[entry.status].class">
                                {{ SCAN_STATUS_THEME[entry.status].label }}
                            </Badge>
                            <Badge variant="secondary" class="text-[11px]">
                                {{ entry.source === 'camera' ? 'Kamera' : 'Manual' }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-xl border border-dashed border-border/80 px-3 py-8 text-center text-sm text-muted-foreground">
                    Belum ada riwayat scan.
                </p>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border border-border/70">
            <CardContent class="p-4">
                <div class="flex items-start gap-2.5">
                    <CircleHelp class="mt-0.5 size-4 text-muted-foreground" />
                    <div class="space-y-1 text-xs text-muted-foreground">
                        <p class="font-medium text-foreground">Catatan mode frontend-only</p>
                        <p>Validasi QR saat ini memakai data mock pada halaman ini, belum tersambung ke database/backend.</p>
                        <p>Contoh kode yang valid: <span class="font-medium text-foreground">DOSCOM-2026-REG-001</span></p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
