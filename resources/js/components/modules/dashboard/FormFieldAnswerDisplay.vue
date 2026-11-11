<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { onWindowEvent } from '@/adapters/browser/domEvents';
import { openUrlInNewTab, saveBlobAsFile } from '@/adapters/browser/fileDownload';
import { currentOrigin } from '@/adapters/browser/navigation';
import { downloadFormAnswerFile } from '@/adapters/http/forms';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { Button } from '@/components/ui/button';
import { getFormFieldOptionRows, formFieldApiType, formFieldBuilderType } from '@/lib/formFieldOptions';
import { readFieldMetadata } from '@/lib/formFieldMetadata';
import { cn } from '@/lib/utils';
import { Download, FileText, FileImage, ExternalLink, Maximize2, Image as ImageIcon, X } from 'lucide-vue-next';

const props = defineProps<{
    /** When null, file detection uses a light heuristic (e.g. form-uploads paths). */
    field: IFormField | null;
    value: unknown;
}>();

const lightboxUrl = ref<string | null>(null);

function onEscapeClose(e: KeyboardEvent): void {
    if (e.key === 'Escape') {
        closeLightbox();
    }
}

function closeLightbox(): void {
    lightboxUrl.value = null;
}

function openLightbox(url: string): void {
    lightboxUrl.value = url;
}

let removeEscapeListener: (() => void) | null = null;

watch(lightboxUrl, (v) => {
    removeEscapeListener?.();
    removeEscapeListener = null;
    if (v) {
        removeEscapeListener = onWindowEvent('keydown', onEscapeClose);
    }
});

onUnmounted(() => {
    removeEscapeListener?.();
});

function plainText(): string {
    const v = props.value;
    if (v == null || v === '') {
        return '—';
    }
    if (Array.isArray(v)) {
        return v.length ? v.map(String).join(', ') : '—';
    }
    return String(v);
}

const publicFileUrl = computed((): string | null => {
    if (typeof props.value !== 'string') {
        return null;
    }
    const t = props.value.trim();
    if (!t) {
        return null;
    }
    return resolveAnswerFileUrl(t);
});

function isImageHref(href: string): boolean {
    const path = (href.split('?')[0] ?? '').toLowerCase();
    return /\.(jpe?g|png|gif|webp|avif|bmp|svg)$/i.test(path);
}

/**
 * Jawaban file memakai route privat yang sudah diotorisasi server.
 * Jangan melewatkan route itu ke normalizer storage publik: ia akan mengubah
 * `/admin/form-answers/files/...` menjadi `/storage/admin/...` dan memecahkan
 * preview gambar/PDF.
 */
function resolveAnswerFileUrl(value: string): string | null {
    const pathname = new URL(value, currentOrigin()).pathname;
    const segments = pathname.split('/').filter(Boolean);
    if (segments[0] === 'admin' && segments[1] === 'form-answers' && segments[2] === 'files') return value;

    return normalizePublicStorageUrl(value) || null;
}

function isPdfHref(href: string): boolean {
    const path = (href.split('?')[0] ?? '').toLowerCase();
    return path.endsWith('.pdf');
}

function basenameFromHref(href: string | null): string {
    if (!href) {
        return 'berkas';
    }
    try {
        const path = new URL(href, currentOrigin()).pathname;
        const last = path.split('/').filter(Boolean).pop() ?? 'berkas';
        return decodeURIComponent(last) || 'berkas';
    } catch {
        const seg = href.split('/').filter(Boolean).pop() ?? 'berkas';
        try {
            return decodeURIComponent(seg) || 'berkas';
        } catch {
            return seg || 'berkas';
        }
    }
}

const storedFileLabel = computed(() => basenameFromHref(publicFileUrl.value));

const treatsAsFile = computed((): boolean => {
    if (!props.field) {
        if (typeof props.value !== 'string') {
            return false;
        }
        const s = props.value.trim();
        return s.startsWith('form-uploads/') || /^https?:\/\//i.test(s) || s.startsWith('/storage/');
    }
    if (formFieldApiType(props.field) === 'fileUpload') {
        return true;
    }
    const bt = formFieldBuilderType(props.field);
    return bt === 'file_upload' || bt === 'image_upload' || bt === 'fileUpload';
});

const preferImagePreview = computed((): boolean => {
    if (!publicFileUrl.value) {
        return false;
    }
    if (!props.field) {
        return isImageHref(publicFileUrl.value);
    }
    const bt = formFieldBuilderType(props.field);
    if (bt === 'image_upload') {
        return true;
    }
    return isImageHref(publicFileUrl.value);
});

const isPdfPreview = computed((): boolean => {
    if (!publicFileUrl.value) {
        return false;
    }
    return isPdfHref(publicFileUrl.value);
});

const attachmentCardClass = cn(
    'overflow-hidden rounded-2xl border border-border/50 bg-card shadow-sm',
    'ring-1 ring-black/[0.04] dark:ring-white/[0.06]'
);

const attachmentToolbarClass = cn(
    'flex flex-col gap-3 border-b border-border/40 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5',
    'bg-gradient-to-b from-muted/[0.35] to-card/80'
);

function openFileInNewTab(url: string): void {
    openUrlInNewTab(url);
}

const isMultipleChoice = computed((): boolean => {
    if (!props.field) {
        return false;
    }
    const meta = readFieldMetadata(props.field);
    const bt = formFieldBuilderType(props.field);
    return (
        props.field.type === 'checkbox' ||
        (props.field.type === 'select' && Boolean(meta.is_multiple)) ||
        bt === 'checkbox'
    );
});

const isRadioLike = computed((): boolean => {
    if (!props.field) {
        return false;
    }
    const bt = formFieldBuilderType(props.field);
    return props.field.type === 'radio' || bt === 'radio' || bt === 'yes_no';
});

const isSelectSingle = computed((): boolean => {
    if (!props.field) {
        return false;
    }
    return props.field.type === 'select' && !isMultipleChoice.value;
});

const matchedOptionRows = computed(() => {
    if (!props.field) {
        return [];
    }
    const rows = getFormFieldOptionRows(props.field);
    const v = props.value;
    if (Array.isArray(v)) {
        const set = new Set(v.map(String));
        return rows.filter((r) => set.has(r.label));
    }
    if (typeof v === 'string' && v !== '') {
        return rows.filter((r) => r.label === v);
    }
    return [];
});

const showChoiceMedia = computed(() => isMultipleChoice.value || isRadioLike.value || isSelectSingle.value);

async function downloadStoredFile(url: string, suggestedName: string): Promise<void> {
    const name = suggestedName || basenameFromHref(url);
    try {
        const blob = await downloadFormAnswerFile(url);
        if (!saveBlobAsFile(blob, name)) {
            openUrlInNewTab(url, name);
        }
    } catch {
        openUrlInNewTab(url, name);
    }
}

/** Label singkat jenis lampiran untuk toolbar */
const attachmentKindLabel = computed((): string => {
    if (!publicFileUrl.value) return 'Berkas';
    if (preferImagePreview.value) return 'Gambar';
    if (isPdfPreview.value) return 'PDF';
    return 'Berkas';
});
</script>

<template>
    <div class="space-y-3">
        <template v-if="treatsAsFile && publicFileUrl">
            <!-- Gambar: toolbar + area pratinjau terpisah, tanpa footer tebal -->
            <div v-if="preferImagePreview" :class="attachmentCardClass">
                <div :class="attachmentToolbarClass">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-emerald-500/[0.12] text-emerald-700 dark:text-emerald-400"
                            aria-hidden="true"
                        >
                            <FileImage class="size-5" stroke-width="2" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {{ attachmentKindLabel }}
                            </p>
                            <p class="text-foreground truncate text-sm font-semibold" :title="storedFileLabel">
                                {{ storedFileLabel }}
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="h-9 shrink-0 gap-1.5 px-3 text-[13px] font-medium"
                        @click="openLightbox(publicFileUrl)"
                    >
                        <Maximize2 class="size-3.5 opacity-80" aria-hidden="true" />
                        Layar penuh
                    </Button>
                </div>
                <button
                    type="button"
                    class="group bg-muted/20 hover:bg-muted/35 focus-visible:ring-ring relative w-full p-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    @click="openLightbox(publicFileUrl)"
                >
                    <img
                        :src="publicFileUrl"
                        alt=""
                        class="mx-auto max-h-[min(15rem,44vh)] max-w-full rounded-lg object-contain shadow-sm ring-1 ring-black/5 transition duration-200 group-hover:opacity-[0.98] dark:ring-white/10"
                        loading="lazy"
                    />
                </button>
            </div>

            <!-- PDF: toolbar + viewport iframe -->
            <div v-else-if="isPdfPreview" :class="attachmentCardClass">
                <div :class="attachmentToolbarClass">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-rose-500/[0.11] text-rose-700 dark:text-rose-300"
                            aria-hidden="true"
                        >
                            <FileText class="size-5" stroke-width="2" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {{ attachmentKindLabel }}
                            </p>
                            <p class="text-foreground truncate text-sm font-semibold" :title="storedFileLabel">
                                {{ storedFileLabel }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 px-3 text-[13px]"
                            @click="openFileInNewTab(publicFileUrl)"
                        >
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                            Tab baru
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-foreground h-9 gap-1.5 px-3 text-[13px]"
                            @click="downloadStoredFile(publicFileUrl, storedFileLabel)"
                        >
                            <Download class="size-3.5 opacity-80" aria-hidden="true" />
                            Unduh
                        </Button>
                    </div>
                </div>
                <div class="bg-muted/20 p-3 sm:p-4">
                    <div
                        class="bg-background ring-border/50 dark:bg-background/80 overflow-hidden rounded-xl shadow-inner ring-1"
                    >
                        <iframe
                            :src="publicFileUrl"
                            title="Pratinjau PDF"
                            class="h-[min(22rem,50vh)] w-full border-0"
                        />
                    </div>
                </div>
            </div>

            <!-- Berkas lain: kartu kompak satu baris aksi -->
            <div v-else :class="attachmentCardClass">
                <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-5">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div
                            class="bg-primary/10 text-primary grid size-10 shrink-0 place-items-center rounded-full"
                            aria-hidden="true"
                        >
                            <FileText class="size-5" stroke-width="2" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {{ attachmentKindLabel }}
                            </p>
                            <p class="text-foreground truncate text-sm font-semibold" :title="storedFileLabel">
                                {{ storedFileLabel }}
                            </p>
                        </div>
                    </div>
                    <div class="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto sm:justify-end">
                        <Button
                            type="button"
                            variant="default"
                            size="sm"
                            class="h-9 flex-1 gap-1.5 px-4 text-[13px] sm:flex-initial"
                            @click="openFileInNewTab(publicFileUrl)"
                        >
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                            Buka
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9 flex-1 gap-1.5 px-4 text-[13px] sm:flex-initial"
                            @click="downloadStoredFile(publicFileUrl, storedFileLabel)"
                        >
                            <Download class="size-3.5" aria-hidden="true" />
                            Unduh
                        </Button>
                    </div>
                </div>
            </div>
        </template>

        <ul v-else-if="showChoiceMedia && matchedOptionRows.length > 0" class="flex flex-col gap-2.5">
            <li
                v-for="row in matchedOptionRows"
                :key="row.label"
                class="border-border/70 bg-card/40 flex items-center gap-3 rounded-xl border px-3.5 py-2.5"
            >
                <img
                    v-if="row.imageSrc"
                    :src="row.imageSrc"
                    alt=""
                    class="border-border size-11 shrink-0 rounded-lg border object-cover"
                    loading="lazy"
                />
                <span
                    v-else
                    class="bg-muted/50 text-muted-foreground flex size-11 shrink-0 items-center justify-center rounded-full"
                >
                    <ImageIcon class="size-5" aria-hidden="true" />
                </span>
                <span class="text-foreground text-sm leading-snug font-medium">{{ row.label }}</span>
            </li>
        </ul>

        <p v-else class="text-foreground text-[0.875rem] leading-[1.6] whitespace-pre-wrap">
            {{ plainText() }}
        </p>

        <Teleport to="body">
            <div
                v-if="lightboxUrl"
                class="fixed inset-0 z-[300] flex flex-col items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-label="Pratinjau gambar"
                @click.self="closeLightbox"
            >
                <div
                    class="absolute top-0 right-0 left-0 z-10 flex items-center justify-between gap-3 px-4 py-3 sm:px-6"
                >
                    <p class="min-w-0 flex-1 truncate text-xs font-medium text-white/80">
                        <span class="hidden sm:inline">Pratinjau lampiran · ketuk luar gambar atau tutup</span>
                        <span class="sm:hidden">Pratinjau</span>
                    </p>
                    <Button
                        radius="icon"
                        type="button"
                        variant="secondary"
                        size="icon-sm"
                        class="shrink-0 border border-white/15 bg-white/10 text-white hover:bg-white/20"
                        aria-label="Tutup pratinjau"
                        @click="closeLightbox"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </Button>
                </div>
                <img
                    :src="lightboxUrl"
                    alt=""
                    class="max-h-[min(88dvh,920px)] max-w-full rounded-xl object-contain shadow-2xl ring-1 ring-white/10"
                    @click.stop
                />
            </div>
        </Teleport>
    </div>
</template>
