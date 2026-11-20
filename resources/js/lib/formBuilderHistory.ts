/**
 * Riwayat undo/redo yang murni dan bisa diuji tanpa Vue.
 * `past` menyimpan keadaan sebelumnya, `future` menyimpan keadaan yang bisa diulang.
 */

export interface IHistoryState<T> {
    past: T[];
    present: T;
    future: T[];
}

export const FORM_BUILDER_HISTORY_LIMIT = 50;

export function createHistory<T>(present: T): IHistoryState<T> {
    return { past: [], present, future: [] };
}

/** Keadaan baru selalu menghapus cabang `future`, sama seperti editor pada umumnya. */
export function pushHistory<T>(
    state: IHistoryState<T>,
    next: T,
    limit: number = FORM_BUILDER_HISTORY_LIMIT
): IHistoryState<T> {
    if (next === state.present) return state;

    const past = [...state.past, state.present];

    return {
        past: past.length > limit ? past.slice(past.length - limit) : past,
        present: next,
        future: [],
    };
}

export function undoHistory<T>(state: IHistoryState<T>): IHistoryState<T> {
    if (state.past.length === 0) return state;

    const previous = state.past[state.past.length - 1] as T;

    return {
        past: state.past.slice(0, -1),
        present: previous,
        future: [state.present, ...state.future],
    };
}

export function redoHistory<T>(state: IHistoryState<T>): IHistoryState<T> {
    if (state.future.length === 0) return state;

    const next = state.future[0] as T;

    return {
        past: [...state.past, state.present],
        present: next,
        future: state.future.slice(1),
    };
}
