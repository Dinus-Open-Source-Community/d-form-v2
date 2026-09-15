<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Clock3, Users } from 'lucide-vue-next';
import {
    SCAN_STATUS_THEME,
    type GlobalScanQueueSession,
    type ScanEntry,
    type ScanResult,
} from '@/lib/qrScanUi';

const props = withDefaults(
    defineProps<{
        scanResult: ScanResult | null;
        queue?: GlobalScanQueueSession[];
        feedOnline?: boolean;
        scanHistory: ScanEntry[];
        logExpanded?: boolean;
        logQuery?: string;
    }>(),
    {
        queue: () => [],
        feedOnline: false,
        logExpanded: false,
        logQuery: '',
    },
);

const emit = defineEmits<{
    'update:logQuery': [value: string];
    'toggle-log': [];
    'clear-history': [];
    clearHistory: [];
}>();

function isOprecKind(kind: string): boolean {
    return kind === 'oprec';
}

function kindLabel(kind: string): string {
    return isOprecKind(kind) ? 'OPREC' : 'EVENT';
}

function kindBadgeClass(kind: string): string {
    return isOprecKind(kind) ? 'border-violet-500/40 text-violet-600' : 'border-sky-500/40 text-sky-600';
}

function eventTitleOf(value: ScanResult | ScanEntry): string {
    return value.eventTitle || '';
}

function hasEventContext(value: ScanResult | ScanEntry): boolean {
    const title = eventTitleOf(value);
    return title !== '' && title !== '-';
}

function padQueueNumber(value: number | null): string {
    if (value === null) {
        return '-';
    }

    return String(value).padStart(2, '0');
}

function heroIdentifier(result: ScanResult): string {
    if (!hasEventContext(result)) {
        return result.email;
    }

    if (isOprecKind(result.eventKind)) {
        return `${result.email} · Antrian #${padQueueNumber(result.queueNumber)}`;
    }

    if (result.status === 'success') {
        return `${result.email} · tiket antre dikirim`;
    }

    return result.email;
}

const WAITING_PREVIEW = 3;

function visibleWaiting(session: GlobalScanQueueSession): GlobalScanQueueSession['waiting'] {
    return session.waiting.slice(0, WAITING_PREVIEW);
}

function hiddenWaitingCount(session: GlobalScanQueueSession): number {
    return Math.max(session.waitingCount - visibleWaiting(session).length, 0);
}

function sourceLabel(source: string): string {
    return source === 'manual' ? 'Manual' : 'Kamera';
}

const filteredHistory = computed<ScanEntry[]>(() => {
    const query = props.logQuery.trim().toLowerCase();

    if (query.length === 0) {
        return props.scanHistory;
    }

    return props.scanHistory.filter((entry) => {
        const haystack = `${entry.name} ${entry.email} ${entry.eventTitle || ''}`.toLowerCase();

        return haystack.includes(query);
    });
});

function onLogQueryInput(value: string): void {
    emit('update:logQuery', value);
}

function onToggleLog(): void {
    emit('toggle-log');
}

function onClearHistory(): void {
    emit('clear-history');
    emit('clearHistory');
}
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
                        <div class="min-w-0 flex-1">
                            <div
                                v-if="hasEventContext(scanResult)"
                                class="mb-1.5 flex items-center gap-2"
                            >
                                <Badge
                                    variant="outline"
                                    :class="['shrink-0 text-[11px]', kindBadgeClass(scanResult.eventKind)]"
                                >
                                    {{ kindLabel(scanResult.eventKind) }}
                                </Badge>
                                <p
                                    class="text-muted-foreground min-w-0 flex-1 truncate text-xs font-medium"
                                    :title="eventTitleOf(scanResult)"
                                >
                                    {{ eventTitleOf(scanResult) }}
                                </p>
                            </div>
                            <p class="text-foreground text-sm font-semibold">{{ scanResult.name }}</p>
                            <p class="text-muted-foreground text-xs">{{ heroIdentifier(scanResult) }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <Badge variant="outline" :class="SCAN_STATUS_THEME[scanResult.status].class">
                                    {{ SCAN_STATUS_THEME[scanResult.status].label }}
                                </Badge>
                                <Badge variant="outline">{{ sourceLabel(scanResult.source) }}</Badge>
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
                    <CardTitle class="text-base font-semibold">Sedang Diproses</CardTitle>
                    <span class="text-muted-foreground flex items-center gap-1.5 text-[11px] font-medium">
                        <span
                            :class="[
                                'inline-block size-1.5 rounded-full',
                                feedOnline ? 'bg-success animate-pulse' : 'bg-muted-foreground/50',
                            ]"
                        />
                        {{ feedOnline ? 'Live' : 'Menghubungkan…' }}
                    </span>
                </div>
            </CardHeader>
            <CardContent class="pt-0">
                <div v-if="queue.length > 0" class="space-y-4">
                    <div class="space-y-2">
                        <p class="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">
                            Antrian oprec live
                        </p>
                        <div
                            v-for="session in queue"
                            :key="session.sessionId"
                            class="border-border/70 bg-background rounded-xl border px-3 py-3"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <Badge
                                    variant="outline"
                                    class="border-violet-500/40 shrink-0 text-[11px] text-violet-600"
                                >
                                    OPREC
                                </Badge>
                                <span
                                    class="text-foreground min-w-0 flex-1 truncate text-xs font-medium"
                                    :title="session.label"
                                >
                                    {{ session.label }}
                                </span>
                            </div>

                            <div
                                v-if="session.nowServing"
                                class="mt-2.5 flex items-center gap-2.5 rounded-lg bg-violet-500/10 px-2.5 py-2"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-violet-500/15 text-sm font-semibold text-violet-600"
                                >
                                    {{ padQueueNumber(session.nowServing.queueNumber) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold tracking-wide text-violet-600 uppercase">
                                        Sedang dilayani
                                    </p>
                                    <p class="text-foreground truncate text-sm font-semibold">
                                        {{ session.nowServing.name }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-else
                                class="text-muted-foreground border-border/80 mt-2.5 rounded-lg border border-dashed px-2.5 py-2 text-xs"
                            >
                                Belum ada peserta yang dipanggil.
                            </p>

                            <div v-if="session.waiting.length > 0" class="mt-2.5">
                                <p class="text-muted-foreground mb-1.5 flex items-center gap-1 text-[11px] font-medium">
                                    <Users class="size-3" />
                                    Menunggu ({{ session.waitingCount }})
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="(waiter, index) in visibleWaiting(session)"
                                        :key="`${waiter.queueNumber}-${index}`"
                                        class="bg-muted/60 text-foreground inline-flex max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-[11px]"
                                    >
                                        <span class="text-muted-foreground font-mono">
                                            {{ padQueueNumber(waiter.queueNumber) }}
                                        </span>
                                        <span class="truncate">{{ waiter.name }}</span>
                                    </span>
                                    <span
                                        v-if="hiddenWaitingCount(session) > 0"
                                        class="text-muted-foreground inline-flex items-center rounded-full px-2 py-0.5 text-[11px]"
                                    >
                                        +{{ hiddenWaitingCount(session) }} lagi
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="border-border/80 text-muted-foreground rounded-xl border border-dashed px-3 py-8 text-center text-sm"
                >
                    Belum ada antrian yang berjalan.
                </p>
            </CardContent>
        </Card>

        <Card class="border-border/70 rounded-2xl border">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between gap-3">
                    <CardTitle class="text-base font-semibold">Riwayat Scan</CardTitle>
                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 text-xs"
                            :disabled="scanHistory.length === 0"
                            @click="onClearHistory"
                        >
                            Bersihkan
                        </Button>
                        <Button variant="outline" size="sm" class="h-8 text-xs" @click="onToggleLog">
                            {{ logExpanded ? 'Sembunyikan' : `Tampilkan (${scanHistory.length})` }}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="pt-0">
                <div v-if="logExpanded" class="space-y-2">
                    <Input
                        :model-value="logQuery"
                        placeholder="Cari nama, kode, atau acara…"
                        @update:model-value="onLogQueryInput"
                    />
                    <div v-if="filteredHistory.length > 0" class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="entry in filteredHistory"
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

                            <p
                                v-if="hasEventContext(entry)"
                                class="text-muted-foreground mt-1.5 flex min-w-0 items-center gap-1.5 truncate text-xs"
                                :title="eventTitleOf(entry)"
                            >
                                <span
                                    :class="[
                                        'inline-block size-1.5 shrink-0 rounded-full',
                                        isOprecKind(entry.eventKind) ? 'bg-violet-500' : 'bg-sky-500',
                                    ]"
                                />
                                <span class="truncate">{{ eventTitleOf(entry) }}</span>
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <Badge variant="outline" :class="SCAN_STATUS_THEME[entry.status].class">
                                    {{ SCAN_STATUS_THEME[entry.status].label }}
                                </Badge>
                                <Badge variant="secondary" class="text-[11px]">
                                    {{ sourceLabel(entry.source) }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="border-border/80 text-muted-foreground rounded-xl border border-dashed px-3 py-8 text-center text-sm"
                    >
                        {{ logQuery.trim().length > 0 ? 'Tidak ada hasil untuk pencarian ini.' : 'Belum ada riwayat scan.' }}
                    </p>
                </div>
                <button
                    v-else
                    type="button"
                    class="border-border/80 text-muted-foreground hover:bg-muted/40 w-full rounded-xl border border-dashed px-3 py-6 text-center text-sm transition-colors"
                    @click="onToggleLog"
                >
                    Log disembunyikan agar fokus scan. Buka saat ada komplain ({{ scanHistory.length }}).
                </button>
            </CardContent>
        </Card>
    </div>
</template>
