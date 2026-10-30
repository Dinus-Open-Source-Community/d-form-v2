<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    GripVertical,
    ArrowUp,
    ArrowDown,
    Pencil,
    Trash2,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    ChevronDown,
    PlusCircle,
} from 'lucide-vue-next';
import type { BuilderField } from '@/types/form-builder';
import FormBuilderBannerBlock from './FormBuilderBannerBlock.vue';
import FormSuccessMessageCard from './FormSuccessMessageCard.vue';
import type { FormBannerState } from './formBanner';

const PAGE_SIZE = 5;

const formTitle = defineModel<string>('formTitle', { required: true });
const formDescription = defineModel<string>('formDescription', { required: true });
const successContent = defineModel<string>('successContent', { required: true });
const banner = defineModel<FormBannerState>('banner', { required: true });

const props = defineProps<{
    hideOnMobileSettings: boolean;
    bannerPreviewSrc: string;
    isEmpty: boolean;
    isDraggingOverCanvas: boolean;
    formFields: BuilderField[];
    selectedFieldId: string | null;
    dropIndicatorIndex: number;
    dragSourceId: string | null;
    showSuccessZone: boolean;
    /** Endpoint `POST {form}/images` untuk upload banner. */
    bannerUploadUrl?: string;
}>();

defineEmits<{
    canvasDragOver: [e: DragEvent];
    canvasDragLeave: [e: DragEvent];
    canvasDrop: [e: DragEvent];
    gapDragEnter: [index: number];
    canvasDragStart: [e: DragEvent, field: BuilderField, index: number];
    dragEnd: [];
    selectField: [id: string, isMobile?: boolean];
    updateField: [field: BuilderField];
    manageField: [id: string];
    deleteField: [id: string];
    duplicateField: [id: string];
    moveField: [id: string, dir: -1 | 1];
    openAddSheet: [];
    remove: [];
}>();

const currentPage = ref(1);

/** Maks karakter judul & subtitle (validasi frontend saja — payload tidak diubah) */
const TITLE_MAX = 200;
const SUBTITLE_MAX = 500;

const titleLength = computed(() => formTitle.value.length);
const subtitleLength = computed(() => formDescription.value.length);

function capInput(value: string, max: number): string {
    return value.length > max ? value.slice(0, max) : value;
}

function onTitleInput(e: Event): void {
    const el = e.target as HTMLInputElement;
    el.value = capInput(el.value, TITLE_MAX);
    formTitle.value = el.value;
}

function onSubtitleInput(e: Event): void {
    const el = e.target as HTMLTextAreaElement;
    el.value = capInput(el.value, SUBTITLE_MAX);
    formDescription.value = el.value;
}

const totalPages = computed(() => {
    if (props.formFields.length === 0) return 1;
    return Math.max(1, Math.ceil(props.formFields.length / PAGE_SIZE));
});

const sliceStart = computed(() => (currentPage.value - 1) * PAGE_SIZE);

const paginatedFields = computed(() => props.formFields.slice(sliceStart.value, sliceStart.value + PAGE_SIZE));

const trailingGapIndex = computed(() => sliceStart.value + paginatedFields.value.length);

function goPrev(): void {
    currentPage.value = Math.max(1, currentPage.value - 1);
}

function goNext(): void {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
}

watch(
    () => props.formFields.length,
    (n, prev) => {
        const maxPage = Math.max(1, n === 0 ? 1 : Math.ceil(n / PAGE_SIZE));
        if (currentPage.value > maxPage) currentPage.value = maxPage;
        if (prev !== undefined && n > prev) {
            currentPage.value = maxPage;
        }
    }
);

watch(
    () => [props.selectedFieldId, props.formFields.length] as const,
    () => {
        const id = props.selectedFieldId;
        if (!id || props.formFields.length === 0) return;
        const idx = props.formFields.findIndex((f) => f.id === id);
        if (idx === -1) return;
        const p = Math.floor(idx / PAGE_SIZE) + 1;
        if (p !== currentPage.value) currentPage.value = p;
    }
);

function gapActive(globalIdx: number): boolean {
    return props.dropIndicatorIndex === globalIdx;
}

/** Ada aktivitas drag (dari palet atau reorder) — tampilkan UI bantu penyisipan */
const showDropChrome = computed(
    () => props.dropIndicatorIndex >= 0 || props.dragSourceId != null || props.isDraggingOverCanvas
);
</script>

<template>
    <div
        :class="[
            'flex min-h-full justify-center px-4 py-5 sm:px-6 sm:py-6 lg:pt-5 lg:pb-12',
            hideOnMobileSettings && 'hidden lg:flex',
        ]"
    >
        <div class="flex min-h-0 w-full max-w-[480px] flex-col sm:max-w-[520px]">
            <div
                v-if="isEmpty"
                class="border-border bg-muted/30 text-muted-foreground mb-3 hidden items-center gap-1.5 rounded-lg border border-dashed px-3 py-2 text-xs whitespace-nowrap lg:flex"
            >
                <span>Tarik dari panel kiri</span>
                <span class="text-muted-foreground/40" aria-hidden="true">→</span>
                <span>letakkan di garis biru</span>
                <span class="text-muted-foreground/40" aria-hidden="true">•</span>
                <span>grip kiri untuk urutan</span>
            </div>

            <!-- Section utama: banner + judul/deskripsi + field, dipisah divider -->
            <section
                class="border-border bg-card flex min-h-0 flex-col overflow-hidden rounded-2xl border shadow-sm"
                :class="showDropChrome && !isEmpty ? 'ring-primary/25 ring-2 ring-inset' : ''"
                @dragover.prevent="$emit('canvasDragOver', $event)"
                @dragleave="$emit('canvasDragLeave', $event)"
                @drop="$emit('canvasDrop', $event)"
            >
                <!-- Banner -->
                <div class="border-border/70 border-b">
                    <FormBuilderBannerBlock
                        v-model:banner="banner"
                        :banner-preview-src="bannerPreviewSrc"
                        :upload-url="bannerUploadUrl ?? ''"
                        variant="plain"
                    />
                </div>

                <!-- Formulir Pendaftaran: judul + deskripsi (editable di kanvas) -->
                <section class="border-border/70 border-b">
                    <div class="space-y-3 px-4 py-4 sm:px-5 sm:py-5">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <Label for="f-title" class="text-xs font-medium">
                                    Title <span class="text-destructive">*</span>
                                </Label>
                                <span
                                    class="text-muted-foreground/70 text-[10px] font-medium tabular-nums"
                                    :class="titleLength >= TITLE_MAX ? 'text-destructive/80' : ''"
                                    >{{ titleLength }}/{{ TITLE_MAX }}</span
                                >
                            </div>
                            <div
                                class="border-border/90 bg-background rounded-lg border px-3 py-2 shadow-sm sm:px-3.5"
                            >
                                <input
                                    id="f-title"
                                    :value="formTitle"
                                    :maxlength="TITLE_MAX"
                                    placeholder="Judul form"
                                    class="font-display text-foreground placeholder:text-muted-foreground/65 w-full border-0 bg-transparent p-0 text-sm leading-snug font-semibold tracking-tight focus:ring-0 focus:outline-none sm:text-base"
                                    @input="onTitleInput"
                                />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <Label for="f-description" class="text-xs font-medium">Subtitle</Label>
                                <span
                                    class="text-muted-foreground/70 text-[10px] font-medium tabular-nums"
                                    :class="subtitleLength >= SUBTITLE_MAX ? 'text-destructive/80' : ''"
                                    >{{ subtitleLength }}/{{ SUBTITLE_MAX }}</span
                                >
                            </div>
                            <div
                                class="border-border/90 bg-background rounded-lg border px-3 py-2 shadow-sm sm:px-3.5"
                            >
                                <textarea
                                    id="f-description"
                                    :value="formDescription"
                                    :maxlength="SUBTITLE_MAX"
                                    rows="2"
                                    placeholder="Deskripsi singkat untuk peserta…"
                                    class="text-muted-foreground placeholder:text-muted-foreground/65 min-h-[2.75rem] w-full resize-none border-0 bg-transparent p-0 text-sm leading-relaxed focus:ring-0 focus:outline-none"
                                    @input="onSubtitleInput"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Field form -->
                <section>
                    <div class="px-5 py-7 sm:px-7 sm:py-8">
                        <div
                            v-if="isEmpty && !isDraggingOverCanvas"
                            class="flex flex-col items-center justify-center py-8 text-center"
                        >
                            <div
                                class="empty-state-float text-muted-foreground/70 mb-4 grid size-14 place-items-center rounded-2xl border border-dashed border-border bg-muted/40 shadow-sm"
                                aria-hidden="true"
                            >
                                <svg
                                    width="30"
                                    height="30"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M6.5 3.5h7l4 4V20a.75.75 0 0 1-.75.75H6.5a.75.75 0 0 1-.75-.75V4.25a.75.75 0 0 1 .75-.75Z"
                                        fill="currentColor"
                                        opacity="0.08"
                                        class="empty-state-doc"
                                    />
                                    <path
                                        d="M13.5 3.75V8a.75.75 0 0 0 .75.75h4M6.5 3.5h7l4 4V20a.75.75 0 0 1-.75.75H6.5a.75.75 0 0 1-.75-.75V4.25a.75.75 0 0 1 .75-.75Z"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M9 13h6M9 16.25h4"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                        opacity="0.75"
                                    />
                                    <path
                                        class="empty-state-plus"
                                        d="M14.5 7.25v-3M13 5.75h3"
                                        stroke="currentColor"
                                        stroke-width="1.4"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>
                            <p class="text-foreground text-sm font-semibold">Kanvas masih kosong</p>
                            <p class="text-muted-foreground mt-1 max-w-[260px] text-sm leading-relaxed">
                                <span class="hidden lg:inline">Tarik komponen dari kiri untuk menambah field.</span>
                                <span class="lg:hidden">Gunakan tombol di bawah untuk menambah field pertama.</span>
                            </p>
                            <Button size="sm" class="mt-5 lg:hidden" @click="$emit('openAddSheet')">
                                Tambah field
                            </Button>
                        </div>

                        <div
                            v-if="isEmpty && isDraggingOverCanvas"
                            class="border-primary/50 bg-primary/[0.08] text-primary hidden min-h-[140px] flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed transition-all duration-300 lg:flex"
                        >
                            <div class="bg-primary/15 grid size-12 place-items-center rounded-full">
                                <PlusCircle class="size-6" />
                            </div>
                            <p class="text-sm font-semibold">Lepaskan di sini</p>
                            <p class="text-muted-foreground max-w-xs px-4 text-center text-xs">
                                Field baru akan ditambahkan pada posisi ini.
                            </p>
                        </div>

                        <div
                            v-if="!isEmpty"
                            class="flex min-h-0 flex-col"
                        >
                            <div
                                data-testid="form-builder-fields-scroll"
                                class="pr-0.5"
                            >
                                <TransitionGroup name="fb-field" tag="div" class="flex flex-col gap-3 lg:gap-4">
                                    <div
                                        v-for="(field, localIdx) in paginatedFields"
                                        :key="field.id"
                                        class="fb-field-row"
                                    >
                                        <!-- Zona drop: area besar + chip saat aktif -->
                                        <div
                                            class="relative z-10 -my-1 hidden min-h-5 w-full py-1 lg:block"
                                            @dragenter.prevent="$emit('gapDragEnter', sliceStart + localIdx)"
                                            @dragover.prevent
                                        >
                                            <div
                                                class="flex min-h-[1.25rem] w-full items-center justify-center transition-all duration-200"
                                                :class="
                                                    gapActive(sliceStart + localIdx)
                                                        ? 'py-1'
                                                        : showDropChrome
                                                          ? 'py-0.5'
                                                          : ''
                                                "
                                            >
                                                <div
                                                    v-if="gapActive(sliceStart + localIdx)"
                                                    class="border-primary bg-primary/15 text-primary flex w-full max-w-full scale-[1.01] items-center justify-center gap-2 rounded-xl border-2 border-dashed px-3 py-2.5 shadow-md transition-transform duration-200"
                                                >
                                                    <PlusCircle class="size-4 shrink-0" />
                                                    <span class="text-xs font-bold tracking-wide"
                                                        >Sisipkan di sini</span
                                                    >
                                                </div>
                                                <div
                                                    v-else-if="showDropChrome"
                                                    class="border-muted-foreground/35 bg-muted/40 h-1 w-full max-w-[90%] rounded-full border border-dashed"
                                                />
                                            </div>
                                        </div>

                                        <div
                                            class="flex flex-col gap-3 transition-[opacity,transform,box-shadow] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] lg:flex-row lg:items-start lg:gap-4"
                                            :class="[
                                                dragSourceId === field.id ? 'scale-[0.99] opacity-45' : 'opacity-100',
                                            ]"
                                        >
                                            <div
                                                class="hidden shrink-0 flex-col items-center gap-1.5 pt-1 lg:flex"
                                                :class="dragSourceId === field.id ? 'scale-[0.98]' : ''"
                                            >
                                                <div
                                                    class="border-border bg-card text-muted-foreground hover:border-primary/45 hover:bg-primary/8 hover:text-primary ring-border/60 hover:ring-primary/25 grid size-10 cursor-grab place-items-center rounded-full border-2 border-transparent shadow-sm ring-1 transition-all duration-200 active:cursor-grabbing"
                                                    draggable="true"
                                                    title="Seret untuk memindahkan urutan — atau pakai tombol naik/turun"
                                                    @dragstart="
                                                        $emit('canvasDragStart', $event, field, sliceStart + localIdx)
                                                    "
                                                    @dragend="$emit('dragEnd')"
                                                >
                                                    <GripVertical class="size-4" />
                                                </div>
                                                <div class="flex flex-col gap-0.5">
                                                    <Button
                                                        radius="icon"
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        class="border-border/80 hover:border-primary/40 hover:bg-primary/5 size-8 shadow-sm transition-all duration-200"
                                                        :disabled="sliceStart + localIdx === 0"
                                                        title="Pindah ke atas"
                                                        @click="$emit('moveField', field.id, -1)"
                                                    >
                                                        <ChevronUp class="size-4" />
                                                    </Button>
                                                    <Button
                                                        radius="icon"
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        class="border-border/80 hover:border-primary/40 hover:bg-primary/5 size-8 shadow-sm transition-all duration-200"
                                                        :disabled="sliceStart + localIdx === formFields.length - 1"
                                                        title="Pindah ke bawah"
                                                        @click="$emit('moveField', field.id, 1)"
                                                    >
                                                        <ChevronDown class="size-4" />
                                                    </Button>
                                                </div>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <FieldRenderer
                                                    :field="field"
                                                    :is-selected="selectedFieldId === field.id"
                                                    @select="$emit('selectField', field.id)"
                                                    @update-field="$emit('updateField', $event)"
                                                    @manage="$emit('manageField', field.id)"
                                                    @delete="$emit('deleteField', field.id)"
                                                    @duplicate="$emit('duplicateField', field.id)"
                                                />
                                            </div>
                                        </div>

                                        <div
                                            class="border-border bg-muted/30 mt-2 flex items-center justify-between gap-1 rounded-xl border px-2 py-1.5 lg:hidden"
                                        >
                                            <div class="flex items-center gap-0.5">
                                                <Button
                                                    radius="icon"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    :disabled="sliceStart + localIdx === 0"
                                                    aria-label="Naikkan field"
                                                    @click="$emit('moveField', field.id, -1)"
                                                >
                                                    <ArrowUp class="size-4" />
                                                </Button>
                                                <Button
                                                    radius="icon"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    :disabled="sliceStart + localIdx === formFields.length - 1"
                                                    aria-label="Turunkan field"
                                                    @click="$emit('moveField', field.id, 1)"
                                                >
                                                    <ArrowDown class="size-4" />
                                                </Button>
                                            </div>
                                            <div class="flex items-center gap-0.5">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 gap-1 px-2 text-xs font-semibold"
                                                    @click="$emit('selectField', field.id, true)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                    Edit
                                                </Button>
                                                <Button
                                                    radius="icon"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    aria-label="Hapus field"
                                                    @click="$emit('deleteField', field.id)"
                                                >
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </TransitionGroup>

                                <div
                                    class="relative z-10 mt-1 hidden min-h-5 w-full py-1 lg:block"
                                    @dragenter.prevent="$emit('gapDragEnter', trailingGapIndex)"
                                    @dragover.prevent
                                >
                                    <div class="flex min-h-[1.25rem] w-full items-center justify-center">
                                        <div
                                            v-if="gapActive(trailingGapIndex)"
                                            class="border-primary bg-primary/15 text-primary flex w-full scale-[1.01] items-center justify-center gap-2 rounded-xl border-2 border-dashed px-3 py-2.5 shadow-md transition-transform duration-200"
                                        >
                                            <PlusCircle class="size-4 shrink-0" />
                                            <span class="text-xs font-bold tracking-wide">Sisipkan di akhir</span>
                                        </div>
                                        <div
                                            v-else-if="showDropChrome"
                                            class="border-muted-foreground/35 bg-muted/40 h-1 w-full max-w-[90%] rounded-full border border-dashed"
                                        />
                                    </div>
                                </div>
                            </div>

                            <nav
                                v-if="totalPages > 1"
                                class="border-border/80 mt-5 flex shrink-0 flex-wrap items-center justify-between gap-3 border-t pt-5"
                                aria-label="Halaman field"
                            >
                                <p class="text-muted-foreground text-xs font-medium">
                                    Field {{ sliceStart + 1 }}–{{
                                        Math.min(sliceStart + paginatedFields.length, formFields.length)
                                    }}
                                    dari
                                    {{ formFields.length }}
                                </p>
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-9 gap-1 px-3"
                                        :disabled="currentPage <= 1"
                                        @click="goPrev"
                                    >
                                        <ChevronLeft class="size-4" />
                                        <span class="hidden sm:inline">Sebelumnya</span>
                                    </Button>
                                    <span class="text-muted-foreground text-xs font-semibold tabular-nums">
                                        {{ currentPage }} / {{ totalPages }}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-9 gap-1 px-3"
                                        :disabled="currentPage >= totalPages"
                                        @click="goNext"
                                    >
                                        <span class="hidden sm:inline">Berikutnya</span>
                                        <ChevronRight class="size-4" />
                                    </Button>
                                </div>
                            </nav>
                        </div>
                    </div>
                </section>

                <!-- Zona "Pesan setelah submit": segmen terakhir section utama (ala Google Forms) -->
                <FormSuccessMessageCard
                    v-model:success-content="successContent"
                    :show="showSuccessZone"
                    @remove="$emit('remove')"
                />
            </section>

        </div>
    </div>
</template>

<style scoped>
.fb-field-move {
    transition:
        transform 0.42s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.28s ease;
}

/* Empty state: ikon dokumen melayang halus, path "+" berdenyut pelan */
.empty-state-float {
    animation: empty-float 3.2s ease-in-out infinite;
}

@keyframes empty-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

.empty-state-float:hover {
    animation-play-state: paused;
}

.empty-state-plus {
    transform-origin: center;
    animation: empty-plus-pulse 2.4s ease-in-out infinite;
}

@keyframes empty-plus-pulse {
    0%,
    100% {
        opacity: 0.25;
    }
    50% {
        opacity: 1;
    }
}
</style>
