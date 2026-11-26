<script setup lang="ts">
import { computed, ref } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import DuOptionCoursePickerDialog from '@/components/modules/builder/DuOptionCoursePickerDialog.vue';
import {
    buildDuOptionMappingRows,
    classifyDuOptionRows,
    duCatalogStatus,
    readDuOptionMap,
    storedDuMappingState,
    withDuOptionMapping,
    type IDuOptionMappingRow,
    type TDuStoredMappingState,
} from '@/components/modules/builder/duFieldMapping';
import type { IDuCourseOption, TDuOptionMap } from '@/types/du';
import type { IBuilderField } from '@/types/form-builder';

const props = withDefaults(
    defineProps<{
        field: IBuilderField;
        catalog: IDuCourseOption[];
        loading?: boolean;
        syncing?: boolean;
        error?: string;
    }>(),
    { loading: false, syncing: false, error: '' }
);

const emit = defineEmits<{
    (event: 'update:field', field: IBuilderField): void;
    (event: 'sync'): void;
}>();

const catalogStatus = computed(() => duCatalogStatus(props.loading, props.error !== '', props.catalog.length));
const rows = computed<IDuOptionMappingRow[]>(() =>
    buildDuOptionMappingRows(props.field.options, readDuOptionMap(props.field.metadata), props.catalog)
);
const optionRowsState = computed(() => classifyDuOptionRows(props.field.options.length, rows.value.length));
/** Sync hanya meremajakan katalog DU; tanpa opsi field tidak ada yang bisa dipetakan. */
const hasMappableOptions = computed<boolean>(() => optionRowsState.value !== 'no-options');

const hasCourseCatalog = computed<boolean>(() => props.catalog.length > 0);

/** Emit isi mapping berikutnya; metadata lain milik field tetap utuh. */
function emitMapping(next: TDuOptionMap): void {
    emit('update:field', {
        ...props.field,
        metadata: { ...props.field.metadata, du_option_map: next },
    });
}

function warningState(row: IDuOptionMappingRow): TDuStoredMappingState {
    return storedDuMappingState(row.stored !== null, row.activeCourse !== null, catalogStatus.value);
}

function storedCourseLabel(row: IDuOptionMappingRow): string {
    if (row.stored === null) return '';

    return row.activeCourse?.title ?? (row.stored.course_title || row.stored.course_uid);
}

function onCourseChange(row: IDuOptionMappingRow, uid: string): void {
    if (uid === '') return;

    const course = props.catalog.find((option) => option.uid === uid);

    if (course === undefined) return;

    emitMapping(withDuOptionMapping(readDuOptionMap(props.field.metadata), row.value, course));
}

function applySuggestion(row: IDuOptionMappingRow): void {
    if (row.suggestion === null) return;

    emitMapping(withDuOptionMapping(readDuOptionMap(props.field.metadata), row.value, row.suggestion));
}

const pickerRow = ref<IDuOptionMappingRow | null>(null);
const pickerOpen = computed<boolean>(() => pickerRow.value !== null);
const pickerSelectedUid = computed<string>(() => pickerRow.value?.stored?.course_uid ?? '');

function openPicker(row: IDuOptionMappingRow): void {
    pickerRow.value = row;
}

function closePicker(): void {
    pickerRow.value = null;
}

/** Pilih satu course dari dialog; mapping langsung disimpan lewat jalur yang sama. */
function selectCourse(uid: string): void {
    const row = pickerRow.value;

    if (row === null) return;

    onCourseChange(row, uid);
    closePicker();
}
</script>

<template>
    <div class="border-border/60 bg-muted/20 flex flex-col gap-3 rounded-xl border px-4 py-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-medium">Hubungkan opsi ke DU</p>
                <p class="text-muted-foreground text-xs">
                    Opsi terkunci selama redeem DU aktif. Pilih course DU untuk setiap opsi kelas.
                </p>
                <p v-if="hasMappableOptions" class="text-muted-foreground text-[11px]">
                    Sync memperbarui katalog DU, bukan opsi field.
                </p>
            </div>
            <Button
                v-if="hasMappableOptions"
                type="button"
                variant="outline"
                class="h-11 text-xs font-bold"
                :disabled="syncing || loading"
                :aria-busy="syncing"
                @click="emit('sync')"
            >
                <RefreshCw class="mr-1 size-3.5" aria-hidden="true" />
                {{ syncing ? 'Menyinkronkan…' : 'Sync katalog DU' }}
            </Button>
        </div>

        <p v-if="loading" class="text-muted-foreground text-xs" role="status">Memuat katalog kelas DU…</p>
        <p v-if="error" role="alert" class="text-destructive text-xs">{{ error }}</p>

        <p
            v-if="catalogStatus === 'empty'"
            class="border-border bg-background text-muted-foreground rounded-lg border px-3 py-2 text-xs"
        >
            Belum ada course DU yang cocok. Jalankan Sync kelas setelah DU membuatnya.
        </p>

        <ul v-if="rows.length > 0" class="flex flex-col gap-2">
            <li
                v-for="(row, index) in rows"
                :key="`${row.value}-${index}`"
                data-testid="du-option-row"
                class="border-border bg-background flex flex-col gap-2 rounded-lg border p-3"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-foreground text-sm font-medium break-words">{{ row.value }}</p>
                        <p class="text-muted-foreground text-xs break-words">
                            {{ row.stored ? `Terhubung · ${storedCourseLabel(row)}` : 'Belum terhubung' }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 shrink-0 text-xs font-semibold"
                        :disabled="loading || !hasCourseCatalog"
                        :aria-label="`Pilih course DU untuk ${row.value}`"
                        @click="openPicker(row)"
                    >
                        {{ row.stored ? 'Ubah' : 'Pilih' }}
                    </Button>
                </div>

                <div
                    v-if="row.suggestion"
                    class="border-primary/20 bg-primary/5 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2"
                >
                    <p class="text-xs">
                        Terdeteksi: <span class="font-semibold">{{ row.suggestion.title }}</span>
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 text-xs font-bold"
                        @click="applySuggestion(row)"
                    >
                        Gunakan
                    </Button>
                </div>

                <p v-if="warningState(row) === 'loading'" class="text-muted-foreground text-[11px]" role="status">
                    Memeriksa katalog kelas DU…
                </p>
                <p v-else-if="warningState(row) === 'error'" class="text-muted-foreground text-[11px]" role="status">
                    Gagal memuat katalog. Status course belum bisa dipastikan.
                </p>
                <p v-else-if="warningState(row) === 'missing'" class="text-[11px] text-amber-700 dark:text-amber-300">
                    Course {{ storedCourseLabel(row) }} tidak ada di katalog aktif.
                </p>
            </li>
        </ul>

        <p v-else-if="optionRowsState === 'no-options'" class="text-muted-foreground text-xs">
            Field ini belum punya opsi. Matikan redeem DU, tambahkan opsi, lalu aktifkan lagi.
        </p>
        <p v-else class="text-muted-foreground text-xs">Opsi tanpa label tidak bisa dipetakan.</p>

        <DuOptionCoursePickerDialog
            :open="pickerOpen"
            :option-value="pickerRow?.value ?? ''"
            :courses="props.catalog"
            :selected-uid="pickerSelectedUid"
            :loading="props.loading"
            :error="props.error"
            @close="closePicker"
            @select="selectCourse"
        />
    </div>
</template>
