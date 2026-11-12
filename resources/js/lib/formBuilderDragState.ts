/**
 * State machine sesi drag kanvas form builder.
 *
 * Semua transisi UI drag (marker sisip, ring kanvas, opacity sumber) diturunkan dari
 * state ini. Sesi dihentikan oleh `drop`, `end`, `escape`, atau `blur` — satu jalur
 * reset yang idempotent sehingga kegagalan di tengah drop tidak meninggalkan marker.
 */

/** Asal drag: item palette (field baru) atau grip field di kanvas (reorder). */
export type TFormBuilderDragOrigin = 'palette' | 'field';

/** State sesi drag kanvas; `isActive` false berarti tidak ada sesi berjalan. */
export interface IFormBuilderDragState {
    readonly isActive: boolean;
    readonly origin: TFormBuilderDragOrigin | null;
    readonly sourceFieldId: string | null;
    /** Indeks sisip di section aktif; -1 = tidak ada target. */
    readonly dropIndicatorIndex: number;
    /** Pointer berada di atas area drop kanvas (ring + dropzone kosong). */
    readonly isOverCanvas: boolean;
    /**
     * Counter batas kanvas (0/1). `dragenter` di batas menaikkan ke 1 dan `dragleave`
     * di batas menurunkannya ke 0; transisi antar anak kanvas tidak mengubah nilai.
     */
    readonly canvasDepth: number;
}

/** Event yang boleh mengubah sesi drag. */
export type TFormBuilderDragEvent =
    | { readonly type: 'start'; readonly origin: TFormBuilderDragOrigin; readonly sourceFieldId?: string | null }
    | { readonly type: 'canvasEnter' }
    | { readonly type: 'canvasLeave' }
    | { readonly type: 'canvasOver'; readonly isCanvasEmpty: boolean }
    | { readonly type: 'gapEnter'; readonly index: number }
    | { readonly type: 'drop' }
    | { readonly type: 'end' }
    | { readonly type: 'escape' }
    | { readonly type: 'blur' };

/** State diam: tidak ada sesi, tidak ada marker, tidak ada hover. */
export const initialDragState: IFormBuilderDragState = {
    isActive: false,
    origin: null,
    sourceFieldId: null,
    dropIndicatorIndex: -1,
    isOverCanvas: false,
    canvasDepth: 0,
};

/**
 * Tutup sesi sekali. State diam dikembalikan apa adanya agar pemanggilan berulang
 * (mis. drop lalu dragend) tidak memicu render tambahan.
 */
function terminate(state: IFormBuilderDragState): IFormBuilderDragState {
    return state.isActive ? initialDragState : state;
}

/**
 * Hitung state drag berikutnya. Murni: tanpa DOM, tanpa efek samping.
 *
 * Aturan:
 * - enter/leave/over/gap hanya berlaku saat sesi aktif;
 * - marker dibersihkan hanya saat dragleave batas kanvas menurunkan counter ke 0;
 * - drop/end/escape/blur selalu menutup sesi, termasuk saat sesi sudah tidak aktif.
 */
export function reduceDragEvent(state: IFormBuilderDragState, event: TFormBuilderDragEvent): IFormBuilderDragState {
    switch (event.type) {
        case 'start':
            return {
                isActive: true,
                origin: event.origin,
                sourceFieldId: event.sourceFieldId ?? null,
                dropIndicatorIndex: -1,
                isOverCanvas: false,
                canvasDepth: 0,
            };

        case 'canvasEnter':
            // Idempotent: dragenter berulang (termasuk dari anak kanvas) tidak menumpuk counter.
            if (!state.isActive || state.isOverCanvas) return state;
            return { ...state, isOverCanvas: true, canvasDepth: 1 };

        case 'canvasLeave': {
            if (!state.isActive) return state;
            const canvasDepth = Math.max(0, state.canvasDepth - 1);
            if (canvasDepth > 0) return { ...state, canvasDepth };
            return { ...state, canvasDepth: 0, isOverCanvas: false, dropIndicatorIndex: -1 };
        }

        case 'canvasOver':
            if (!state.isActive) return state;
            return {
                ...state,
                isOverCanvas: true,
                canvasDepth: Math.max(1, state.canvasDepth),
                dropIndicatorIndex:
                    state.dropIndicatorIndex === -1 && event.isCanvasEmpty ? 0 : state.dropIndicatorIndex,
            };

        case 'gapEnter':
            if (!state.isActive) return state;
            return {
                ...state,
                isOverCanvas: true,
                dropIndicatorIndex: event.index,
            };

        case 'drop':
        case 'end':
        case 'escape':
        case 'blur':
            return terminate(state);
    }
}
