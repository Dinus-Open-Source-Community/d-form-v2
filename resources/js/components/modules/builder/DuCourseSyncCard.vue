<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import DuCoursePickerDialog from '@/components/modules/builder/DuCoursePickerDialog.vue';
import { useDuCourseCatalog } from '@/hooks/useDuCourseCatalog';
import { useDuCourseSync } from '@/hooks/useDuCourseSync';
import {
    canApplyCourseSelection,
    missingCourseUids,
    readSelectedCourseUids,
    removeSelectedCourseUid,
    selectActiveCourseUids,
} from '@/lib/duCourseSelection';
import { DU_CATALOG_ERROR_MESSAGE } from '@/constants/du';
import { formatDateTime } from '@/lib/dateTime';
import { routes } from '@/lib/routes';
import type { IDuCourseOption } from '@/types/du';
import type { IBuilderField, IFieldOptionEntry } from '@/types/form-builder';

const props = defineProps<{ field: IBuilderField }>();

const emit = defineEmits<{
    (event: 'update:field', field: IBuilderField): void;
}>();

const {
    catalog,
    loading,
    error: catalogError,
    ensureLoaded,
    setCatalog,
} = useDuCourseCatalog(routes.admin.duCourses.index);
const {
    syncing,
    error: syncError,
    courses: syncedCourses,
    syncedAt,
    sync,
} = useDuCourseSync(routes.admin.duCourses.sync);

const pickerOpen = ref(false);
const catalogUpdateError = ref('');

watch(
    () => props.field.id,
    () => {
        void ensureLoaded();
    },
    { immediate: true }
);

const syncedAtLabel = computed((): string =>
    syncedAt.value === '' ? '' : formatDateTime({ value: syncedAt.value, variant: 'date-time-medium' })
);

/** Pilihan eksplisit dari metadata; `null` = semua opsi yang sedang tampil. */
const explicitSelection = computed<string[] | null>(() => readSelectedCourseUids(props.field.metadata));
const currentOptionUids = computed<string[]>(() => props.field.options.map((option) => option.id));
const pickerSelection = computed<string[]>(() => explicitSelection.value ?? currentOptionUids.value);
const missingCount = computed<number>(() =>
    canApplyCourseSelection(loading.value, catalogError.value)
        ? missingCourseUids(explicitSelection.value, catalog.value).length
        : 0
);
const errorMessage = computed<string>(() => syncError.value || catalogError.value || catalogUpdateError.value);

function optionFromCourse(course: IDuCourseOption): IFieldOptionEntry {
    return { id: course.uid, type: 'text', label: course.title, imageUrl: '' };
}

/** Simpan opsi tampilan dan pilihan eksplisit dalam satu update field. */
function emitField(options: IFieldOptionEntry[], selectedUids: string[]): void {
    emit('update:field', {
        ...props.field,
        options,
        metadata: { ...props.field.metadata, selected_course_uids: selectedUids },
    });
}

function openPicker(): void {
    void ensureLoaded();
    pickerOpen.value = true;
}

function applySelection(uids: string[]): void {
    const selected = new Set(uids);
    const options = catalog.value.filter((course) => selected.has(course.uid)).map(optionFromCourse);

    emitField(options, uids);
    pickerOpen.value = false;
}

/** Hapus satu kelas; metadata ditulis eksplisit agar pilihan implisit tidak hilang. */
function removeCourse(option: IFieldOptionEntry): void {
    const nextUids = removeSelectedCourseUid(explicitSelection.value, currentOptionUids.value, option.id);
    const options = props.field.options.filter((current) => current.id !== option.id);

    emitField(options, nextUids);
}

/** Sync memperbarui cache server; opsi tetap disusun dari pilihan yang tersimpan. */
async function syncCourses(): Promise<void> {
    catalogUpdateError.value = '';

    const ok = await sync();

    if (!ok) return;

    if (!setCatalog(syncedCourses.value)) {
        catalogUpdateError.value = DU_CATALOG_ERROR_MESSAGE;

        return;
    }

    const allowed = new Set(selectActiveCourseUids(readSelectedCourseUids(props.field.metadata), catalog.value));

    emit('update:field', {
        ...props.field,
        options: catalog.value.filter((course) => allowed.has(course.uid)).map(optionFromCourse),
    });
}
</script>

<template>
    <div class="border-border/60 bg-muted/20 flex flex-col gap-3 rounded-xl border px-4 py-3">
        <div>
            <p class="text-sm font-medium">Kelas DU untuk form ini</p>
            <p class="text-muted-foreground text-xs">
                Hanya kelas terpilih yang tampil untuk peserta. Sinkron katalog tidak mengubah pilihan.
            </p>
        </div>

        <ul v-if="field.options.length > 0" class="flex flex-col gap-2">
            <li
                v-for="(option, index) in field.options"
                :key="option.id"
                class="border-border bg-background flex min-h-11 items-center justify-between gap-2 rounded-xl border p-2.5 pl-3"
            >
                <div class="min-w-0">
                    <span class="text-muted-foreground text-[10px] font-bold tracking-wider uppercase">
                        Kelas {{ index + 1 }}
                    </span>
                    <p class="text-foreground mt-1 text-sm font-medium break-words">{{ option.label }}</p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-11 shrink-0"
                    :aria-label="`Hapus kelas ${option.label}`"
                    @click="removeCourse(option)"
                >
                    <X class="size-4" aria-hidden="true" />
                </Button>
            </li>
        </ul>
        <p v-else-if="loading" role="status" class="text-muted-foreground text-xs">Memuat katalog kelas DU…</p>
        <p v-else-if="catalogError === ''" class="text-muted-foreground text-xs">
            Belum ada kelas dipilih. Tekan Tambah kelas untuk memilih.
        </p>

        <p v-if="missingCount > 0" role="status" class="text-[11px] text-amber-700 dark:text-amber-300">
            {{ missingCount }} kelas terpilih tidak ada di katalog aktif. Jalankan Sync kelas atau perbarui pilihan.
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <Button type="button" variant="outline" size="sm" class="text-xs font-bold" @click="openPicker">
                Tambah kelas
            </Button>
        </div>

        <p v-if="errorMessage" role="alert" class="text-destructive text-xs">{{ errorMessage }}</p>

        <DuCoursePickerDialog
            :open="pickerOpen"
            :courses="catalog"
            :selected-uids="pickerSelection"
            :loading="loading"
            :error="errorMessage"
            :syncing="syncing"
            :synced-at-label="syncedAtLabel"
            @close="pickerOpen = false"
            @apply="applySelection"
            @sync="syncCourses"
        />
    </div>
</template>
