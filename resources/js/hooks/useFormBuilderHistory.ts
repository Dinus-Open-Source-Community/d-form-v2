import { computed, onUnmounted, ref, toRaw, watch, type ComputedRef, type Ref } from 'vue';
import { onWindowEvent } from '@/adapters/browser/domEvents';
import { createHistory, pushHistory, redoHistory, undoHistory, type IHistoryState } from '@/lib/formBuilderHistory';
import type { IFormBannerState } from '@/components/modules/builder/formBanner';
import type { IBuilderField } from '@/types/form-builder';
import type { IFormRegistrationMetadata } from '@/types/form';

export interface IFormBuilderHistoryModels {
    formTitle: Ref<string>;
    formDescription: Ref<string>;
    successContent: Ref<string>;
    banner: Ref<IFormBannerState>;
    formFields: Ref<IBuilderField[]>;
    formMetadata: Ref<IFormRegistrationMetadata>;
}

interface IFormBuilderSnapshot {
    formTitle: string;
    formDescription: string;
    successContent: string;
    banner: IFormBannerState;
    formFields: IBuilderField[];
    formMetadata: IFormRegistrationMetadata;
}

/** Jeda pencatatan agar satu ketikan panjang tidak menjadi puluhan langkah undo. */
const RECORD_DEBOUNCE_MS = 600;

function readSnapshot(models: IFormBuilderHistoryModels): IFormBuilderSnapshot {
    return {
        formTitle: models.formTitle.value,
        formDescription: models.formDescription.value,
        successContent: models.successContent.value,
        banner: { ...toRaw(models.banner.value) },
        formFields: toRaw(models.formFields.value),
        formMetadata: toRaw(models.formMetadata.value),
    };
}

/** Salinan dalam: state builder memakai objek bersarang (metadata, options, banner). */
function cloneSnapshot(snapshot: IFormBuilderSnapshot): IFormBuilderSnapshot {
    return JSON.parse(JSON.stringify(snapshot)) as IFormBuilderSnapshot;
}

/**
 * Riwayat undo/redo untuk satu halaman builder.
 *
 * Cakupan: header (judul, deskripsi, banner), metadata bagian, dan seluruh field.
 * Setiap perubahan dicatat setelah jeda singkat, lalu undo/redo menulis balik ke model
 * yang sama sehingga autosave berjalan seperti perubahan biasa.
 */
export function useFormBuilderHistory(models: IFormBuilderHistoryModels): {
    canUndo: ComputedRef<boolean>;
    canRedo: ComputedRef<boolean>;
    undo: () => void;
    redo: () => void;
} {
    const history = ref<IHistoryState<IFormBuilderSnapshot>>(createHistory(cloneSnapshot(readSnapshot(models))));
    const applying = ref(false);
    let recordTimer: ReturnType<typeof setTimeout> | null = null;

    function commit(next: IFormBuilderSnapshot): void {
        history.value = pushHistory(history.value, cloneSnapshot(next));
    }

    function scheduleRecord(): void {
        if (applying.value) return;

        if (recordTimer !== null) clearTimeout(recordTimer);

        recordTimer = setTimeout((): void => {
            recordTimer = null;
            commit(readSnapshot(models));
        }, RECORD_DEBOUNCE_MS);
    }

    function apply(snapshot: IFormBuilderSnapshot): void {
        applying.value = true;

        const copy = cloneSnapshot(snapshot);

        models.formTitle.value = copy.formTitle;
        models.formDescription.value = copy.formDescription;
        models.successContent.value = copy.successContent;
        models.banner.value = copy.banner;
        models.formFields.value = copy.formFields;
        models.formMetadata.value = copy.formMetadata;

        void Promise.resolve().then((): void => {
            applying.value = false;
        });
    }

    function undo(): void {
        if (history.value.past.length === 0) return;

        history.value = undoHistory(history.value);
        apply(history.value.present);
    }

    function redo(): void {
        if (history.value.future.length === 0) return;

        history.value = redoHistory(history.value);
        apply(history.value.present);
    }

    watch(
        (): IFormBuilderSnapshot => readSnapshot(models),
        (): void => scheduleRecord(),
        { deep: true, flush: 'post' }
    );

    /** Pintasan standar; di dalam isian teks, biarkan undo bawaan browser yang bekerja. */
    function onKeydown(event: KeyboardEvent): void {
        const target = event.target as HTMLElement | null;
        const tagName = target?.tagName ?? '';
        const isTypingSurface =
            tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT' || target?.isContentEditable === true;

        if (isTypingSurface || !(event.metaKey || event.ctrlKey)) return;

        const key = event.key.toLowerCase();

        if (key === 'z' && !event.shiftKey) {
            event.preventDefault();
            undo();

            return;
        }

        if ((key === 'z' && event.shiftKey) || key === 'y') {
            event.preventDefault();
            redo();
        }
    }

    const releaseKeydown = onWindowEvent('keydown', onKeydown);

    onUnmounted((): void => {
        releaseKeydown();

        if (recordTimer !== null) clearTimeout(recordTimer);
    });

    return {
        canUndo: computed<boolean>(() => history.value.past.length > 0),
        canRedo: computed<boolean>(() => history.value.future.length > 0),
        undo,
        redo,
    };
}
