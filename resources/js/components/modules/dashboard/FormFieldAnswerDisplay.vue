<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { onWindowEvent } from '@/adapters/browser/domEvents';
import { currentOrigin } from '@/adapters/browser/navigation';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { extractFormAnswerFileUrl } from '@/lib/formAnswerFileUrl';
import { Button } from '@/components/ui/button';
import FileTypeTile from '@/components/core/FileTypeTile.vue';
import { getFormFieldOptionRows, formFieldApiType, formFieldBuilderType } from '@/lib/formFieldOptions';
import { readFieldMetadata } from '@/lib/formFieldMetadata';
import { cn } from '@/lib/utils';
import { Download, ExternalLink, Image as ImageIcon, X } from 'lucide-vue-next';

const props = defineProps<{
    /** When null, file detection uses a light heuristic (e.g. form-uploads paths). */
    field: IFormField | null;
    value: unknown;
    fileActionAlignment?: 'start' | 'end';
}>();

const lightboxUrl = ref<string | null>(null);
const lightboxImageFailed = ref(false);
const lightboxLabel = ref('berkas');
const previewImageFailed = ref(false);
const brokenFileKeys = ref<Set<string>>(new Set());

function onEscapeClose(e: KeyboardEvent): void {
    if (e.key === 'Escape') {
        closeLightbox();
    }
}

function closeLightbox(): void {
    lightboxUrl.value = null;
}

function openLightbox(url: string, label: string): void {
    lightboxImageFailed.value = false;
    lightboxLabel.value = label;
    lightboxUrl.value = url;
}

function handleLightboxImageError(): void {
    lightboxImageFailed.value = true;
}

function handlePreviewImageError(): void {
    previewImageFailed.value = true;
}

function handleFilePreviewError(key: string): void {
    brokenFileKeys.value = new Set([...brokenFileKeys.value, key]);
}

function isFilePreviewFailed(key: string): boolean {
    return brokenFileKeys.value.has(key);
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
        return v.length
            ? v
                  .map((item: unknown): string =>
                      typeof item === 'string' && isRawFormUploadPath(item) ? 'Berkas tersedia' : String(item)
                  )
                  .join(', ')
            : '—';
    }
    if (typeof v === 'string' && isRawFormUploadPath(v)) {
        return 'Berkas tersedia';
    }
    return String(v);
}

function isRawFormUploadPath(value: string): boolean {
    return value.startsWith('form-uploads/') || value.includes('/form-uploads/');
}

interface IAnswerFileItem {
    key: string;
    url: string | null;
    fileName: string;
    isImage: boolean;
    isPdf: boolean;
}

function isImageHref(href: string): boolean {
    const path = (href.split('?')[0] ?? '').toLowerCase();
    return /\.(jpe?g|png|gif|webp|avif|bmp|svg)$/i.test(path);
}

function resolveAnswerFileUrl(value: string): string | null {
    const answerFileUrl = extractFormAnswerFileUrl(value);
    if (answerFileUrl) return answerFileUrl;

    if (isRawFormUploadPath(value)) {
        return null;
    }

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

function rawValueFileName(value: string): string {
    const segment = value.split('/').filter(Boolean).pop() ?? 'berkas';
    return segment || 'berkas';
}

const answerFileValues = computed((): string[] => {
    if (Array.isArray(props.value)) {
        return props.value.filter((value): value is string => typeof value === 'string' && value.trim() !== '');
    }
    return typeof props.value === 'string' && props.value.trim() !== '' ? [props.value.trim()] : [];
});

const fileItems = computed((): IAnswerFileItem[] =>
    answerFileValues.value.map((value: string, index: number): IAnswerFileItem => {
        const url = resolveAnswerFileUrl(value);
        const fileName = url ? basenameFromHref(url) : rawValueFileName(value);
        return {
            key: `${index}:${value}`,
            url,
            fileName,
            isImage: url ? isImageHref(url) : false,
            isPdf: url ? isPdfHref(url) : false,
        };
    })
);

watch(answerFileValues, () => {
    brokenFileKeys.value = new Set();
});

const publicFileUrl = computed((): string | null => fileItems.value[0]?.url ?? null);
const firstFileUrl = computed((): string => publicFileUrl.value ?? '');
const storedFileLabel = computed(() => fileItems.value[0]?.fileName ?? 'berkas');

const treatsAsFile = computed((): boolean => {
    if (!props.field) {
        const values = Array.isArray(props.value) ? props.value : [props.value];
        return values.some((value: unknown): boolean => {
            if (typeof value !== 'string') return false;
            const s = value.trim();
            return (
                isRawFormUploadPath(s) ||
                /^https?:\/\//i.test(s) ||
                s.startsWith('/storage/') ||
                extractFormAnswerFileUrl(s) !== null
            );
        });
    }
    if (formFieldApiType(props.field) === 'fileUpload') {
        return true;
    }
    const bt = formFieldBuilderType(props.field);
    return bt === 'file_upload' || bt === 'image_upload' || bt === 'fileUpload';
});

const preferImagePreview = computed((): boolean => {
    if (fileItems.value.length !== 1 || !fileItems.value[0]?.url) {
        return false;
    }
    if (!props.field) {
        return fileItems.value[0].isImage;
    }
    const bt = formFieldBuilderType(props.field);
    if (bt === 'image_upload') {
        return true;
    }
    return fileItems.value[0].isImage;
});

const isPdfPreview = computed((): boolean => {
    if (fileItems.value.length !== 1 || !fileItems.value[0]?.url) {
        return false;
    }
    return fileItems.value[0].isPdf;
});

const attachmentCardClass = cn(
    '@container overflow-hidden rounded-2xl border border-border/50 bg-card shadow-sm',
    'ring-1 ring-black/[0.04] dark:ring-white/[0.06]'
);

const attachmentToolbarClass = cn(
    'flex min-w-0 flex-col gap-3 border-b border-border/40 px-4 py-3.5 @md:flex-row @md:items-start @md:justify-between @md:gap-4 @md:px-5',
    'bg-gradient-to-b from-muted/[0.35] to-card/80'
);

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
        <template v-if="treatsAsFile">
            <div v-if="fileItems.length > 1" data-testid="form-answer-file-list" class="space-y-3">
                <div v-for="item in fileItems" :key="item.key" :class="attachmentCardClass">
                    <div v-if="item.url && item.isImage && !isFilePreviewFailed(item.key)" class="bg-muted/20 p-3">
                        <button
                            type="button"
                            :aria-label="`Buka gambar ${item.fileName} layar penuh`"
                            class="group focus-visible:ring-ring block w-full rounded-lg p-1 text-left focus-visible:ring-2 focus-visible:outline-none"
                            @click="openLightbox(item.url, item.fileName)"
                        >
                            <img
                                :src="item.url"
                                :alt="item.fileName"
                                class="mx-auto max-h-56 w-full rounded-lg object-contain shadow-sm ring-1 ring-black/5 dark:ring-white/10"
                                loading="lazy"
                                @error="handleFilePreviewError(item.key)"
                            />
                        </button>
                    </div>
                    <div v-else class="flex min-w-0 items-center gap-3 p-4">
                        <FileTypeTile :file-name="item.fileName" />
                        <div class="min-w-0 flex-1">
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {{
                                    item.url && item.isImage && isFilePreviewFailed(item.key)
                                        ? 'Pratinjau tidak tersedia'
                                        : item.url
                                          ? item.isPdf
                                              ? 'PDF'
                                              : 'Berkas'
                                          : 'Lampiran'
                                }}
                            </p>
                            <p class="text-foreground block truncate text-sm font-semibold" :title="item.fileName">
                                {{ item.fileName }}
                            </p>
                        </div>
                        <div v-if="item.url" class="flex shrink-0 gap-2">
                            <Button
                                as="a"
                                :href="item.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                variant="default"
                                size="sm"
                                aria-label="Buka berkas"
                            >
                                <ExternalLink class="size-3.5" aria-hidden="true" />
                                Buka
                            </Button>
                            <Button
                                as="a"
                                :href="item.url"
                                :download="item.fileName"
                                variant="outline"
                                size="sm"
                                aria-label="Unduh berkas"
                            >
                                <Download class="size-3.5" aria-hidden="true" />
                                Unduh
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gambar: toolbar + area pratinjau terpisah, tanpa footer tebal -->
            <div v-else-if="preferImagePreview" data-testid="form-answer-image-card" :class="attachmentCardClass">
                <div :class="attachmentToolbarClass">
                    <div class="flex min-w-0 items-start gap-3 @md:flex-1">
                        <FileTypeTile :file-name="storedFileLabel" />
                        <div class="min-w-0 flex-1">
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {{ attachmentKindLabel }}
                            </p>
                            <p
                                data-testid="form-answer-image-filename"
                                class="text-foreground block truncate text-sm font-semibold"
                                :title="storedFileLabel"
                            >
                                {{ storedFileLabel }}
                            </p>
                        </div>
                    </div>
                    <div
                        :class="[
                            'flex w-full flex-wrap items-center gap-2 @md:w-auto @md:shrink-0 @md:flex-nowrap @md:justify-end',
                            props.fileActionAlignment === 'end' ? 'justify-end' : '',
                        ]"
                    >
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 px-3 text-[13px]"
                        >
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                            Buka
                        </Button>
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            :download="storedFileLabel"
                            variant="ghost"
                            size="sm"
                            class="text-foreground h-9 gap-1.5 px-3 text-[13px]"
                        >
                            <Download class="size-3.5" aria-hidden="true" />
                            Unduh
                        </Button>
                    </div>
                </div>
                <button
                    v-if="!previewImageFailed"
                    type="button"
                    aria-label="Buka gambar layar penuh"
                    class="group bg-muted/20 hover:bg-muted/35 focus-visible:ring-ring relative w-full p-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    @click="openLightbox(firstFileUrl, storedFileLabel)"
                >
                    <img
                        :src="firstFileUrl"
                        alt=""
                        class="mx-auto max-h-[min(15rem,44vh)] max-w-full rounded-lg object-contain shadow-sm ring-1 ring-black/5 transition duration-200 group-hover:opacity-[0.98] dark:ring-white/10"
                        loading="lazy"
                        @error="handlePreviewImageError"
                    />
                </button>
                <div
                    v-else
                    role="status"
                    class="bg-muted/20 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-muted-foreground text-sm">Pratinjau gambar tidak tersedia.</p>
                    <div :class="['flex flex-wrap gap-2', props.fileActionAlignment === 'end' ? 'justify-end' : '']">
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 px-3 text-[13px]"
                        >
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                            Buka
                        </Button>
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            :download="storedFileLabel"
                            variant="ghost"
                            size="sm"
                            class="text-foreground h-9 gap-1.5 px-3 text-[13px]"
                        >
                            <Download class="size-3.5" aria-hidden="true" />
                            Unduh
                        </Button>
                    </div>
                </div>
            </div>

            <!-- PDF: toolbar + viewport iframe -->
            <div v-else-if="isPdfPreview" data-testid="form-answer-pdf-card" :class="attachmentCardClass">
                <div :class="attachmentToolbarClass">
                    <div class="flex min-w-0 items-start gap-3 sm:flex-1">
                        <FileTypeTile :file-name="storedFileLabel" />
                        <div class="min-w-0 flex-1">
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {{ attachmentKindLabel }}
                            </p>
                            <p
                                class="text-foreground block min-w-0 truncate text-sm font-semibold"
                                :title="storedFileLabel"
                            >
                                {{ storedFileLabel }}
                            </p>
                        </div>
                    </div>
                    <div
                        :class="[
                            'flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0 sm:justify-end',
                            props.fileActionAlignment === 'end' ? 'justify-end' : '',
                        ]"
                    >
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 px-3 text-[13px]"
                        >
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                            Buka
                        </Button>
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            :download="storedFileLabel"
                            variant="ghost"
                            size="sm"
                            class="text-foreground h-9 gap-1.5 px-3 text-[13px]"
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
                        <iframe :src="firstFileUrl" title="Pratinjau PDF" class="h-[min(22rem,50vh)] w-full border-0" />
                    </div>
                </div>
            </div>

            <!-- Berkas lain: kartu kompak satu baris aksi -->
            <div v-else-if="fileItems.length === 1 && publicFileUrl" :class="attachmentCardClass">
                <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-5">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <FileTypeTile :file-name="storedFileLabel" />
                        <div class="min-w-0 flex-1">
                            <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {{ attachmentKindLabel }}
                            </p>
                            <p
                                class="text-foreground block min-w-0 truncate text-sm font-semibold"
                                :title="storedFileLabel"
                            >
                                {{ storedFileLabel }}
                            </p>
                        </div>
                    </div>
                    <div
                        :class="[
                            'flex w-full shrink-0 flex-wrap gap-2 sm:w-auto sm:justify-end',
                            props.fileActionAlignment === 'end' ? 'justify-end' : '',
                        ]"
                    >
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="default"
                            size="sm"
                            :class="[
                                'h-9 gap-1.5 px-4 text-[13px]',
                                props.fileActionAlignment === 'end' ? '' : 'flex-1 sm:flex-initial',
                            ]"
                        >
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                            Buka
                        </Button>
                        <Button
                            as="a"
                            :href="publicFileUrl"
                            :download="storedFileLabel"
                            variant="outline"
                            size="sm"
                            :class="[
                                'h-9 gap-1.5 px-4 text-[13px]',
                                props.fileActionAlignment === 'end' ? '' : 'flex-1 sm:flex-initial',
                            ]"
                        >
                            <Download class="size-3.5" aria-hidden="true" />
                            Unduh
                        </Button>
                    </div>
                </div>
            </div>
            <div v-else role="status" class="text-muted-foreground rounded-xl border border-dashed p-4 text-sm">
                Lampiran tidak tersedia.
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
                class="fixed inset-0 z-[300] flex flex-col bg-black/90 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                :aria-label="`Pratinjau gambar: ${lightboxLabel}`"
                @click.self="closeLightbox"
            >
                <div class="flex w-full shrink-0 items-start justify-between gap-4 px-1 py-1 sm:px-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-white">Pratinjau lampiran</p>
                        <p class="mt-0.5 text-xs break-words text-white/70" :title="lightboxLabel">
                            {{ lightboxLabel }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="h-9 shrink-0 gap-1.5 border border-white/15 bg-white/10 px-3 text-white hover:bg-white/20"
                        aria-label="Tutup pratinjau"
                        @click="closeLightbox"
                    >
                        <X class="size-4" aria-hidden="true" />
                        Tutup
                    </Button>
                </div>
                <div class="flex min-h-0 flex-1 items-center justify-center py-3" @click.self="closeLightbox">
                    <img
                        v-if="!lightboxImageFailed"
                        :src="lightboxUrl"
                        :alt="lightboxLabel"
                        class="max-h-full max-w-full rounded-xl object-contain shadow-2xl ring-1 ring-white/10"
                        @error="handleLightboxImageError"
                    />
                    <p v-else role="status" class="text-center text-sm text-white/80">
                        Pratinjau gambar tidak tersedia.
                    </p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
