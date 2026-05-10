<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Clock3 } from 'lucide-vue-next';
import { SCAN_STATUS_THEME, type ScanEntry, type ScanResult } from '@/lib/qrScanUi';

defineProps<{
    scanResult: ScanResult | null;
    scanHistory: ScanEntry[];
}>();

defineEmits<{
    clearHistory: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-5">
        <Card class="border-border/70 rounded-2xl border">
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
                            <p class="text-foreground text-sm font-semibold">{{ scanResult.name }}</p>
                            <p class="text-muted-foreground text-xs">{{ scanResult.email }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <Badge variant="outline" :class="SCAN_STATUS_THEME[scanResult.status].class">
                                    {{ SCAN_STATUS_THEME[scanResult.status].label }}
                                </Badge>
                                <Badge variant="outline">{{
                                    scanResult.source === 'camera' ? 'Kamera' : 'Manual'
                                }}</Badge>
                            </div>
                            <p class="text-muted-foreground mt-2 truncate text-xs">
                                Raw code: {{ scanResult.rawCode }}
                            </p>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="border-border/80 bg-muted/20 text-muted-foreground rounded-xl border border-dashed px-3 py-6 text-center text-sm"
                >
                    Belum ada scan. Mulai kamera atau gunakan input manual.
                </div>
            </CardContent>
        </Card>

        <Card class="border-border/70 rounded-2xl border">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between gap-3">
                    <CardTitle class="text-base font-semibold">Riwayat Scan</CardTitle>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 text-xs"
                        :disabled="scanHistory.length === 0"
                        @click="$emit('clearHistory')"
                    >
                        Bersihkan
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="pt-0">
                <div v-if="scanHistory.length > 0" class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                    <div
                        v-for="entry in scanHistory"
                        :key="entry.id"
                        class="border-border/70 bg-background rounded-xl border px-3 py-2.5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-foreground truncate text-sm font-medium">{{ entry.name }}</p>
                                <p class="text-muted-foreground truncate text-xs">{{ entry.email }}</p>
                            </div>
                            <span class="text-muted-foreground flex shrink-0 items-center gap-1 text-xs">
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
                <p
                    v-else
                    class="border-border/80 text-muted-foreground rounded-xl border border-dashed px-3 py-8 text-center text-sm"
                >
                    Belum ada riwayat scan.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
