<script setup lang="ts">
import { computed, ref } from 'vue';
import FieldRenderer from '@/components/modules/builder/FieldRenderer.vue';
import { isFieldDeletionBlocked } from '@/components/modules/builder/fieldMapping';
import { Button } from '@/components/ui/button';
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
    Plus,
    PlusCircle,
} from 'lucide-vue-next';
import type { IBuilderField, IFormBuilderSection } from '@/types/form-builder';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import FormBuilderHeaderSettings from './FormBuilderHeaderSettings.vue';
import FormBuilderReferralBlock from './FormBuilderReferralBlock.vue';
import type { IFormBannerState } from './formBanner';

const formTitle = defineModel<string>('formTitle', { required: true });
const formDescription = defineModel<string>('formDescription', { required: true });
const banner = defineModel<IFormBannerState>('banner', { required: true });

const props = defineProps<{
    bannerPreviewSrc: string;
    /** Error validasi header dari server (tab Editor). */
    titleError?: string;
    descriptionError?: string;
    isEmpty: boolean;
    isDraggingOverCanvas: boolean;
    formFields: IBuilderField[];
    sections: IFormBuilderSection[];
    currentSectionIndex: number;
    selectedFieldId: string | null;
    dropIndicatorIndex: number;
    dragSourceId: string | null;
    /** Fitur referral aktif untuk form ini; blok kode referral hanya tampil bila true. */
    referralEnabled: boolean;
    /** Indeks sisip blok referral di bagian aktif; `null` = tidak tampil di bagian ini. */
    referralInsertIndex: number | null;
    /** Blok referral sedang diseret (dimiringkan seperti field sumber). */
    referralDragging: boolean;
    /** Endpoint `POST {form}/images` untuk upload banner. */
    bannerUploadUrl?: string;
}>();

const emit = defineEmits<{
    canvasDragEnter: [e: DragEvent];
    canvasDragOver: [e: DragEvent];
    canvasDragLeave: [e: DragEvent];
    canvasDrop: [e: DragEvent];
    gapDragEnter: [index: number];
    canvasDragStart: [e: DragEvent, field: IBuilderField, index: number];
    referralDragStart: [e: DragEvent];
    referralDragEnd: [];
    moveReferral: [direction: -1 | 1];
    dragEnd: [];
    selectField: [id: string, isMobile?: boolean];
    manageField: [id: string];
    deleteField: [id: string];
    duplicateField: [id: string];
    moveField: [id: string, dir: -1 | 1];
    openAddSheet: [];
    addSection: [];
    renameSection: [id: string, title: string];
    removeSection: [id: string];
    goToSection: [index: number];
}>();

/** Konfirmasi hapus bagian: field di dalamnya ikut hilang, bagian lain tetap. */
/** Id bagian dikunci saat tombol ditekan, supaya indeks tidak bergeser sebelum konfirmasi. */
const pendingSectionRemoval = ref(false);
const pendingRemovalSectionId = ref('');

function requestSectionRemoval(): void {
    pendingRemovalSectionId.value = props.sections[props.currentSectionIndex]?.id ?? '';
    pendingSectionRemoval.value = true;
}

function confirmRemoveSection(): void {
    const sectionId = pendingRemovalSectionId.value;

    pendingSectionRemoval.value = false;
    pendingRemovalSectionId.value = '';

    if (sectionId !== '') emit('removeSection', sectionId);
}

function gapActive(globalIdx: number): boolean {
    return props.dropIndicatorIndex === globalIdx;
}

/** Ada aktivitas drag (dari palet atau reorder) — tampilkan UI bantu penyisipan */
const showDropChrome = computed(
    () => props.dropIndicatorIndex >= 0 || props.dragSourceId != null || props.isDraggingOverCanvas
);
</script>

<template>
    <!-- pb-28 di mobile: ruang kosong agar konten terakhir (tombol tambah bagian + nav)
         bisa digulir melewati FAB `+` (56px, 84px dari bawah) dan bar aksi bawah. -->
    <div
        class="mx-auto flex min-h-full w-full max-w-[520px] flex-col gap-3 px-4 pt-5 pb-28 sm:px-6 sm:pt-6 sm:pb-2 lg:pt-5 lg:pb-4"
    >
        <FormBuilderHeaderSettings
            v-model:form-title="formTitle"
            v-model:form-description="formDescription"
            v-model:banner="banner"
            :banner-preview-src="bannerPreviewSrc"
            :upload-url="bannerUploadUrl ?? ''"
            :title-error="props.titleError"
            :description-error="props.descriptionError"
        />

        <div
            v-if="isEmpty"
            class="border-border bg-muted/30 text-muted-foreground hidden items-center gap-1.5 rounded-lg border border-dashed px-3 py-2 text-xs whitespace-nowrap lg:flex"
        >
            <span>Tarik dari panel kiri</span>
            <span class="text-muted-foreground/40" aria-hidden="true">→</span>
            <span>letakkan di garis biru</span>
            <span class="text-muted-foreground/40" aria-hidden="true">•</span>
            <span>grip kiri untuk urutan</span>
        </div>

        <section
            class="flex min-h-0 flex-col gap-3"
            :class="showDropChrome && !isEmpty ? 'ring-primary/25 rounded-2xl ring-2 ring-inset' : ''"
            :data-drag-chrome="showDropChrome ? 'active' : undefined"
            data-testid="form-builder-canvas"
            @dragenter="$emit('canvasDragEnter', $event)"
            @dragover.prevent="$emit('canvasDragOver', $event)"
            @dragleave="$emit('canvasDragLeave', $event)"
            @drop="$emit('canvasDrop', $event)"
        >
            <section class="flex flex-col gap-3">
                <div v-if="sections.length > 1" class="flex flex-col gap-1.5">
                    <label for="form-section-name" class="text-muted-foreground text-[11px] font-semibold">
                        Nama bagian
                    </label>
                    <input
                        id="form-section-name"
                        :value="sections[currentSectionIndex]?.title ?? ''"
                        maxlength="60"
                        placeholder="Contoh: Data peserta"
                        aria-label="Nama bagian"
                        class="text-foreground placeholder:text-muted-foreground/60 focus-visible:border-ring focus-visible:ring-ring/30 hover:border-primary/30 border-input bg-card w-full rounded-lg border px-3 py-2 text-sm font-semibold shadow-xs transition-colors outline-none focus-visible:ring-[3px]"
                        @input="
                            $emit(
                                'renameSection',
                                sections[currentSectionIndex]?.id ?? '',
                                ($event.target as HTMLInputElement).value
                            )
                        "
                    />
                </div>
                <div v-if="isEmpty" class="flex min-h-[14rem] flex-col">
                    <div
                        class="flex flex-1 flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-8 text-center transition-colors duration-300"
                        :class="
                            isDraggingOverCanvas
                                ? 'border-primary/50 bg-primary/[0.08] text-primary'
                                : 'border-border bg-card'
                        "
                    >
                        <template v-if="isDraggingOverCanvas">
                            <div class="bg-primary/15 grid size-12 place-items-center rounded-full">
                                <PlusCircle class="size-6" />
                            </div>
                            <p class="mt-3 text-sm font-semibold">Lepaskan di sini</p>
                            <p class="text-muted-foreground mt-1 max-w-xs px-4 text-center text-xs">
                                Field baru akan ditambahkan pada posisi ini.
                            </p>
                        </template>
                        <template v-else>
                            <div
                                class="empty-state-float text-muted-foreground/70 border-border bg-muted/40 mb-4 grid size-14 place-items-center rounded-2xl border shadow-sm"
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
                            <p class="text-foreground text-sm font-semibold">Bagian ini belum memiliki field</p>
                            <p class="text-muted-foreground mt-1 max-w-[260px] text-sm leading-relaxed">
                                <span class="hidden lg:inline">Tarik komponen dari kiri untuk menambah field.</span>
                                <span class="lg:hidden">Gunakan tombol di bawah untuk menambah field pertama.</span>
                            </p>
                            <Button size="sm" class="mt-5 lg:hidden" @click="$emit('openAddSheet')">
                                Tambah field
                            </Button>
                        </template>
                    </div>
                </div>

                <div class="flex min-h-0 flex-col">
                    <div v-if="!isEmpty" data-testid="form-builder-fields-scroll" class="pr-0.5">
                        <TransitionGroup name="fb-field" tag="div" class="flex flex-col gap-3 lg:gap-4">
                            <div v-for="(field, localIdx) in formFields" :key="field.id" class="fb-field-row">
                                <FormBuilderReferralBlock
                                    v-if="referralEnabled && referralInsertIndex === localIdx"
                                    class="mb-3 lg:mt-5 lg:mb-4"
                                    :insert-index="referralInsertIndex"
                                    :field-count="formFields.length"
                                    :dragging="referralDragging"
                                    @drag-start="$emit('referralDragStart', $event)"
                                    @drag-end="$emit('referralDragEnd')"
                                    @move="$emit('moveReferral', $event)"
                                />
                                <!-- Zona drop: area besar + chip saat aktif -->
                                <div
                                    class="relative z-10 -my-1 hidden min-h-5 w-full py-1 lg:block"
                                    :data-drop-gap="localIdx"
                                    @dragenter.prevent="$emit('gapDragEnter', localIdx)"
                                    @dragover.prevent
                                >
                                    <div
                                        class="flex min-h-[1.25rem] w-full items-center justify-center transition-all duration-200"
                                        :class="gapActive(localIdx) ? 'py-1' : showDropChrome ? 'py-0.5' : ''"
                                    >
                                        <div
                                            v-if="gapActive(localIdx)"
                                            data-drag-indicator="gap"
                                            class="border-primary bg-primary/15 text-primary flex w-full max-w-full scale-[1.01] items-center justify-center gap-2 rounded-xl border-2 border-dashed px-3 py-2.5 shadow-md transition-transform duration-200"
                                        >
                                            <PlusCircle class="size-4 shrink-0" />
                                            <span class="text-xs font-bold tracking-wide">Sisipkan di sini</span>
                                        </div>
                                        <div
                                            v-else-if="showDropChrome"
                                            class="border-muted-foreground/35 bg-muted/40 h-1 w-full max-w-[90%] rounded-full border border-dashed"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="relative flex flex-col gap-3 transition-[opacity,transform,box-shadow] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                    :class="[dragSourceId === field.id ? 'scale-[0.99] opacity-45' : 'opacity-100']"
                                >
                                    <div
                                        class="absolute top-1 -left-12 hidden flex-col items-center gap-1.5 pt-1 lg:flex"
                                        :class="dragSourceId === field.id ? 'scale-[0.98]' : ''"
                                    >
                                        <div
                                            class="border-border bg-card text-muted-foreground hover:border-primary/45 hover:bg-primary/8 hover:text-primary ring-border/60 hover:ring-primary/25 grid size-10 cursor-grab place-items-center rounded-full border-2 border-transparent shadow-sm ring-1 transition-all duration-200 active:cursor-grabbing"
                                            draggable="true"
                                            title="Seret untuk memindahkan urutan — atau pakai tombol naik/turun"
                                            @dragstart="$emit('canvasDragStart', $event, field, localIdx)"
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
                                                :disabled="localIdx === 0"
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
                                                :disabled="localIdx === formFields.length - 1"
                                                title="Pindah ke bawah"
                                                @click="$emit('moveField', field.id, 1)"
                                            >
                                                <ChevronDown class="size-4" />
                                            </Button>
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <FieldRenderer
                                            :field="field"
                                            :is-selected="selectedFieldId === field.id"
                                            @open="$emit('manageField', field.id)"
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
                                            :disabled="localIdx === 0"
                                            aria-label="Naikkan field"
                                            @click="$emit('moveField', field.id, -1)"
                                        >
                                            <ArrowUp class="size-4" />
                                        </Button>
                                        <Button
                                            radius="icon"
                                            variant="ghost"
                                            size="icon-sm"
                                            :disabled="localIdx === formFields.length - 1"
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
                                            v-if="!isFieldDeletionBlocked(field)"
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

                        <FormBuilderReferralBlock
                            v-if="referralEnabled && referralInsertIndex === formFields.length"
                            class="mt-3 lg:mt-9"
                            :insert-index="referralInsertIndex"
                            :field-count="formFields.length"
                            :dragging="referralDragging"
                            @drag-start="$emit('referralDragStart', $event)"
                            @drag-end="$emit('referralDragEnd')"
                            @move="$emit('moveReferral', $event)"
                        />

                        <div
                            class="relative z-10 mt-1 hidden min-h-5 w-full py-1 lg:block"
                            :data-drop-gap="formFields.length"
                            @dragenter.prevent="$emit('gapDragEnter', formFields.length)"
                            @dragover.prevent
                        >
                            <div class="flex min-h-[1.25rem] w-full items-center justify-center">
                                <div
                                    v-if="gapActive(formFields.length)"
                                    data-drag-indicator="end"
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

                    <div
                        data-testid="form-builder-add-section-boundary"
                        class="border-border/80 mt-6 flex flex-col items-center gap-2 border-t border-dashed pt-5"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="bg-background h-11 min-h-11 w-full touch-manipulation gap-2 border-dashed px-4 text-sm font-semibold sm:w-auto"
                            aria-label="Tambah bagian setelah ini"
                            @click="$emit('addSection')"
                        >
                            <Plus class="size-4" />
                            <span>Tambah bagian setelah ini</span>
                        </Button>
                        <Button
                            v-if="sections.length > 1"
                            type="button"
                            variant="outline"
                            class="text-destructive border-destructive/40 hover:bg-destructive/10 h-11 min-h-11 w-full touch-manipulation gap-2 px-4 text-sm font-semibold sm:w-auto"
                            aria-label="Hapus bagian ini"
                            @click="requestSectionRemoval"
                        >
                            <Trash2 class="size-4" />
                            <span>Hapus bagian ini</span>
                        </Button>
                    </div>

                    <nav
                        v-if="sections.length > 1"
                        class="border-border/80 mt-5 flex shrink-0 flex-wrap items-center justify-between gap-3 border-t pt-5"
                        aria-label="Navigasi bagian form"
                    >
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1 px-3"
                                :disabled="currentSectionIndex <= 0"
                                aria-label="Sebelumnya"
                                @click="$emit('goToSection', currentSectionIndex - 1)"
                            >
                                <ChevronLeft class="size-4" />
                                <span class="hidden sm:inline">Sebelumnya</span>
                            </Button>
                            <span
                                class="text-muted-foreground min-w-10 text-center text-xs font-semibold tabular-nums"
                                :aria-label="`Bagian ${currentSectionIndex + 1} dari ${sections.length}`"
                            >
                                {{ currentSectionIndex + 1 }} / {{ sections.length }}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1 px-3"
                                :disabled="currentSectionIndex >= sections.length - 1"
                                aria-label="Berikutnya"
                                @click="$emit('goToSection', currentSectionIndex + 1)"
                            >
                                <span class="hidden sm:inline">Berikutnya</span>
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>
                    </nav>
                </div>
            </section>
        </section>

        <ConfirmationModal
            :open="pendingSectionRemoval"
            title="Hapus bagian ini?"
            description="Field di dalam bagian ini ikut dihapus. Bagian lain tidak berubah."
            confirm-text="Hapus bagian"
            variant="destructive"
            confirm-test-id="confirm-remove-section"
            @confirm="confirmRemoveSection"
            @cancel="pendingSectionRemoval = false"
            @update:open="pendingSectionRemoval = $event"
        />
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
