<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Clock3, QrCode, TriangleAlert } from 'lucide-vue-next';
import { SCAN_STATUS_THEME, type IScanEntry, type IScanResult } from '@/lib/qrScanUi';

const props = withDefaults(
    defineProps<{
        scanResult: IScanResult | null;
        scanHistory: IScanEntry[];
        logEntries: IScanEntry[];
        heroEmptyMessage?: string;
        scanBusy?: boolean;
        logExpanded?: boolean;
        logQuery?: string;
        /** Feed lintas meja sedang gagal diperbarui; data lama tetap tampil. */
        feedStale?: boolean;
        /** Jumlah kegagalan feed berturut-turut, dipakai agar status tidak terlihat diam. */
        feedFailureCount?: number;
    }>(),
    {
        heroEmptyMessage: 'Belum ada scan. Mulai kamera atau gunakan input manual.',
        scanBusy: false,
        logExpanded: false,
        logQuery: '',
        feedStale: false,
        feedFailureCount: 0,
    }
);

const registrationCodeInput = defineModel<string>('registrationCodeInput', { required: true });

const emit = defineEmits<{
    'update:logQuery': [value: string];
    'toggle-log': [];
    'clear-history': [];
    clearHistory: [];
    submitManual: [];
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

function eventTitleOf(value: IScanResult | IScanEntry): string {
    return value.eventTitle || '';
}

function hasEventContext(value: IScanResult | IScanEntry): boolean {
    const title = eventTitleOf(value);
    return title !== '' && title !== '-';
}

function padQueueNumber(value: number | null): string {
    if (value === null) {
        return '-';
    }

    return String(value).padStart(2, '0');
}

function heroIdentifier(result: IScanResult): string {
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

function sourceLabel(source: string): string {
    return source === 'manual' ? 'Manual' : 'Kamera';
}

const filteredHistory = computed<IScanEntry[]>(() => {
    const query = props.logQuery.trim().toLowerCase();

    if (query.length === 0) {
        return props.logEntries;
    }

    return props.logEntries.filter((entry) => {
        const haystack = `${entry.name} ${entry.email} ${entry.eventTitle || ''}`.toLowerCase();

        return haystack.includes(query);
    });
});

const emptyLogMessage = computed<string>(() => {
    if (props.logEntries.length === 0 && props.scanHistory.length > 0) {
        return 'Tidak ada riwayat untuk filter acara ini.';
    }

    if (props.logQuery.trim().length > 0) {
        return 'Tidak ada hasil untuk pencarian ini.';
    }

    return 'Belum ada riwayat scan.';
});

function onLogQueryInput(value: string | number): void {
    emit('update:logQuery', String(value));
}

/**
 * Satu live region persisten (selalu ada di DOM) supaya screen reader mengumumkan
 * transisi stale dan pulih; teks tidak berubah selama siklus backoff berjalan.
 */
const feedAnnouncement = ref<string>('');

watch(
    () => props.feedStale,
    (stale: boolean, previous: boolean | undefined): void => {
        if (stale) {
            feedAnnouncement.value = 'Feed scan tertunda. Data lama tetap tampil; sistem mencoba lagi otomatis.';

            return;
        }

        feedAnnouncement.value = previous === true ? 'Feed scan tersambung kembali.' : '';
    },
    { immediate: true }
);

function onToggleLog(): void {
    emit('toggle-log');
}

function onClearHistory(): void {
    emit('clear-history');
    emit('clearHistory');
}

function onSubmitManual(): void {
    emit('submitManual');
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ feedAnnouncement }}</p>

        <div v-if="feedStale" class="border-warning/50 bg-warning/10 rounded-2xl border shadow-sm">
            <div class="flex items-start gap-3 px-4 py-3">
                <span
                    class="border-warning/30 bg-warning/15 text-warning-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border"
                >
                    <TriangleAlert class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-foreground text-sm font-medium">Feed scan tertunda</p>
                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                        Data lama tetap tampil. Sistem mencoba lagi otomatis.
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                        Scan dari perangkat ini tetap tercatat; daftar dari meja lain bisa belum muncul.
                    </p>
                </div>
            </div>
            <!-- Angka percobaan di luar live region persisten agar tidak diumumkan ulang saat backoff. -->
            <p
                v-if="feedFailureCount >= 2"
                class="text-muted-foreground pr-4 pb-3 pl-[3.75rem] text-[11px] leading-relaxed"
            >
                Sudah {{ feedFailureCount }} kali gagal menyegarkan.
            </p>
        </div>

        <Card class="border-border/70 rounded-2xl border">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-semibold">Input Manual</CardTitle>
                <p class="text-muted-foreground text-xs">
                    Isi kode registrasi peserta, mis.
                    <span class="font-mono text-[11px]">OPREC-2026-00001</span>
                    atau kode registrasi event, lalu tekan Proses check-in.
                </p>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="grid gap-2">
                    <Input
                        v-model="registrationCodeInput"
                        class="uppercase placeholder:normal-case"
                        placeholder="OPREC-2026-00001 atau kode registrasi event"
                        :disabled="scanBusy"
                        @keydown.enter.prevent="onSubmitManual"
                    />
                    <Button variant="outline" class="w-full" :disabled="scanBusy" @click="onSubmitManual">
                        <QrCode data-icon="inline-start" />
                        {{ scanBusy ? 'Memproses…' : 'Proses check-in' }}
                    </Button>
                </div>
            </CardContent>
        </Card>

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
                            <div v-if="hasEventContext(scanResult)" class="mb-1.5 flex items-center gap-2">
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
                    {{ heroEmptyMessage }}
                </div>
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
                        {{ emptyLogMessage }}
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
