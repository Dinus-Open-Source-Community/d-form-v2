<script setup lang="ts">
import { computed } from 'vue';
import { Ban } from 'lucide-vue-next';
import type { TExportPhase } from '@/hooks/useExportDownload';
import { formatFileSize } from '@/lib/fileDisplay';

/** Baris status unduhan export: progres byte, teks fase, dan tombol Batalkan. */
const props = withDefaults(
    defineProps<{
        phase: TExportPhase;
        /** Nama unduhan yang ditampilkan, mis. "Registrations CSV". */
        label: string;
        progress?: number;
        /** Server tanpa `Content-Length`: tampilkan byte terkirim, bukan persentase. */
        indeterminate?: boolean;
        loadedBytes?: number;
        currentIndex?: number;
        totalFiles?: number;
        directCount?: number;
        errorMessage?: string;
    }>(),
    {
        progress: 0,
        indeterminate: false,
        loadedBytes: 0,
        currentIndex: 0,
        totalFiles: 1,
        directCount: 0,
        errorMessage: '',
    }
);

const emit = defineEmits<{ cancel: [] }>();

const isRunning = computed<boolean>(() => props.phase === 'running');
const percent = computed<number>(() => Math.min(100, Math.max(0, Math.round(props.progress))));
const batchPosition = computed<string>(() =>
    props.totalFiles > 1 ? ` (${props.currentIndex + 1}/${props.totalFiles})` : ''
);

/** `direct` tidak punya progres byte, jadi status akhirnya "Mulai diunduh". */
const statusText = computed<string>(() => {
    if (props.phase === 'running') {
        if (props.indeterminate) {
            const received = props.loadedBytes > 0 ? ` · ${formatFileSize(props.loadedBytes)}` : '';

            return `Mengunduh ${props.label}${batchPosition.value}${received}`;
        }

        const position = percent.value > 0 ? ` · ${percent.value}%` : '…';

        return `Mengunduh ${props.label}${batchPosition.value}${position}`;
    }

    if (props.phase === 'completed') {
        if (props.directCount === 0) return 'Unduhan selesai';

        return props.directCount === props.totalFiles ? 'Mulai diunduh' : 'Sebagian mulai diunduh';
    }

    if (props.phase === 'error') return props.errorMessage !== '' ? props.errorMessage : 'Unduhan gagal.';

    return '';
});
</script>

<template>
    <div v-if="props.phase !== 'idle'" class="flex min-w-0 items-center gap-2" aria-live="polite">
        <div
            v-if="isRunning"
            class="bg-muted h-1.5 w-20 shrink-0 overflow-hidden rounded-full"
            role="progressbar"
            :aria-valuenow="props.indeterminate ? undefined : percent"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Progres unduhan ${props.label}`"
        >
            <div
                v-if="props.indeterminate"
                class="bg-primary/50 h-full w-full animate-pulse motion-reduce:animate-none"
            />
            <div
                v-else
                class="bg-primary h-full rounded-full transition-[width] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
                :style="{ width: `${percent}%` }"
            />
        </div>
        <span
            class="truncate text-xs"
            :class="props.phase === 'error' ? 'text-destructive font-medium' : 'text-muted-foreground'"
            :title="statusText"
        >
            {{ statusText }}
        </span>
        <button
            v-if="isRunning"
            type="button"
            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/30 inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium transition-colors outline-none focus-visible:ring-[3px]"
            @click="emit('cancel')"
        >
            <Ban class="size-3" aria-hidden="true" />
            Batalkan
        </button>
    </div>
</template>
