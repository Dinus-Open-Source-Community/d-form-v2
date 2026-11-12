import { describe, expect, it } from 'vitest';
import {
    initialDragState,
    reduceDragEvent,
    type IFormBuilderDragState,
    type TFormBuilderDragEvent,
} from '@/lib/formBuilderDragState';

/** Jalankan beberapa event berurutan dari state tertentu. */
function reduceAll(state: IFormBuilderDragState, events: readonly TFormBuilderDragEvent[]): IFormBuilderDragState {
    return events.reduce(reduceDragEvent, state);
}

const PALETTE_STARTED: TFormBuilderDragEvent = { type: 'start', origin: 'palette' };
const FIELD_STARTED: TFormBuilderDragEvent = { type: 'start', origin: 'field', sourceFieldId: 'field-1' };

describe('formBuilderDragState', () => {
    it('mulai dari state diam tanpa marker', () => {
        expect(initialDragState).toEqual({
            isActive: false,
            origin: null,
            sourceFieldId: null,
            dropIndicatorIndex: -1,
            isOverCanvas: false,
            canvasDepth: 0,
        });
    });

    it('start palette menandai sesi tanpa sumber field', () => {
        const state = reduceDragEvent(initialDragState, PALETTE_STARTED);

        expect(state.isActive).toBe(true);
        expect(state.origin).toBe('palette');
        expect(state.sourceFieldId).toBeNull();
        expect(state.dropIndicatorIndex).toBe(-1);
    });

    it('start reorder menyimpan id sumber', () => {
        const state = reduceDragEvent(initialDragState, FIELD_STARTED);

        expect(state.origin).toBe('field');
        expect(state.sourceFieldId).toBe('field-1');
    });

    it('start sesi baru mereset marker dan hover sesi sebelumnya', () => {
        const dirty = reduceAll(initialDragState, [
            PALETTE_STARTED,
            { type: 'gapEnter', index: 3 },
            { type: 'canvasOver', isCanvasEmpty: false },
        ]);

        const restarted = reduceDragEvent(dirty, FIELD_STARTED);

        expect(restarted.dropIndicatorIndex).toBe(-1);
        expect(restarted.isOverCanvas).toBe(false);
        expect(restarted.canvasDepth).toBe(0);
    });

    it('canvasEnter menaikkan counter tanpa mengubah marker', () => {
        const state = reduceDragEvent(reduceDragEvent(initialDragState, PALETTE_STARTED), { type: 'canvasEnter' });

        expect(state.canvasDepth).toBe(1);
        expect(state.isOverCanvas).toBe(true);
        expect(state.dropIndicatorIndex).toBe(-1);
    });

    it('canvasEnter berulang idempotent: anak kanvas tidak menumpuk counter', () => {
        const once = reduceDragEvent(reduceDragEvent(initialDragState, PALETTE_STARTED), { type: 'canvasEnter' });
        const twice = reduceDragEvent(once, { type: 'canvasEnter' });

        expect(twice).toBe(once);
        expect(twice.canvasDepth).toBe(1);
    });

    it('canvasLeave dari counter 0/1 membersihkan marker tetapi sesi tetap aktif', () => {
        const state = reduceAll(initialDragState, [
            PALETTE_STARTED,
            { type: 'canvasEnter' },
            { type: 'canvasEnter' },
            { type: 'gapEnter', index: 2 },
            { type: 'canvasLeave' },
        ]);

        expect(state.canvasDepth).toBe(0);
        expect(state.dropIndicatorIndex).toBe(-1);
        expect(state.isOverCanvas).toBe(false);
        // Sesi masih berjalan sampai drop/end/escape/blur: drag bisa masuk kanvas lagi.
        expect(state.isActive).toBe(true);
        expect(state.origin).toBe('palette');
    });

    it('canvasOver pada section kosong menaruh marker di indeks 0 sekali saja', () => {
        const started = reduceDragEvent(initialDragState, PALETTE_STARTED);
        const first = reduceDragEvent(started, { type: 'canvasOver', isCanvasEmpty: true });
        const second = reduceDragEvent(first, { type: 'canvasOver', isCanvasEmpty: true });

        expect(first.dropIndicatorIndex).toBe(0);
        expect(second.dropIndicatorIndex).toBe(0);
    });

    it('canvasOver pada section berisi tidak mengarang marker', () => {
        const state = reduceDragEvent(reduceDragEvent(initialDragState, PALETTE_STARTED), {
            type: 'canvasOver',
            isCanvasEmpty: false,
        });

        expect(state.dropIndicatorIndex).toBe(-1);
        expect(state.isOverCanvas).toBe(true);
    });

    it('canvasOver tidak menimpa marker gap yang sudah aktif', () => {
        const state = reduceAll(initialDragState, [
            PALETTE_STARTED,
            { type: 'gapEnter', index: 2 },
            { type: 'canvasOver', isCanvasEmpty: true },
        ]);

        expect(state.dropIndicatorIndex).toBe(2);
    });

    it('gapEnter menaruh marker di indeks gap', () => {
        const state = reduceAll(initialDragState, [
            FIELD_STARTED,
            { type: 'canvasEnter' },
            { type: 'gapEnter', index: 4 },
        ]);

        expect(state.dropIndicatorIndex).toBe(4);
        expect(state.isOverCanvas).toBe(true);
        expect(state.canvasDepth).toBe(1);
    });

    it('event hover diabaikan saat tidak ada sesi', () => {
        const enter = reduceDragEvent(initialDragState, { type: 'canvasEnter' });
        const over = reduceDragEvent(initialDragState, { type: 'canvasOver', isCanvasEmpty: true });
        const gap = reduceDragEvent(initialDragState, { type: 'gapEnter', index: 1 });

        expect(enter).toBe(initialDragState);
        expect(over).toBe(initialDragState);
        expect(gap).toBe(initialDragState);
    });

    it('drop menghentikan sesi dan membersihkan semua UI drag', () => {
        const dirty = reduceAll(initialDragState, [
            PALETTE_STARTED,
            { type: 'canvasEnter' },
            { type: 'gapEnter', index: 1 },
        ]);

        expect(reduceDragEvent(dirty, { type: 'drop' })).toBe(initialDragState);
    });

    it('end menghentikan sesi walau marker tidak pernah muncul', () => {
        const state = reduceDragEvent(reduceDragEvent(initialDragState, FIELD_STARTED), { type: 'end' });

        expect(state).toBe(initialDragState);
    });

    it('escape dan blur menghentikan sesi yang sedang hover', () => {
        const dirty = reduceAll(initialDragState, [
            PALETTE_STARTED,
            { type: 'canvasEnter' },
            { type: 'gapEnter', index: 0 },
        ]);

        expect(reduceDragEvent(dirty, { type: 'escape' })).toBe(initialDragState);
        expect(reduceDragEvent(dirty, { type: 'blur' })).toBe(initialDragState);
    });

    it('terminasi berulang idempotent dan tidak membuat state baru', () => {
        const started = reduceDragEvent(initialDragState, PALETTE_STARTED);
        const dropped = reduceDragEvent(started, { type: 'drop' });

        expect(reduceDragEvent(dropped, { type: 'end' })).toBe(initialDragState);
        expect(reduceDragEvent(dropped, { type: 'escape' })).toBe(initialDragState);
        // Event hover yang datang terlambat setelah drop tidak menghidupkan UI lagi.
        expect(reduceDragEvent(dropped, { type: 'canvasOver', isCanvasEmpty: true })).toBe(initialDragState);
    });

    it('regresi stuck state: drop yang gagal di efek samping tetap menutup sesi lebih dulu', () => {
        const dirty = reduceAll(initialDragState, [
            PALETTE_STARTED,
            { type: 'canvasEnter' },
            { type: 'gapEnter', index: 1 },
        ]);

        // Kontrak hook: state dihitung sebelum pembuatan field. Simulasikan efek samping
        // yang melempar setelah transisi drop agar terbukti marker tidak bisa tersangkut.
        const afterDrop = reduceDragEvent(dirty, { type: 'drop' });
        expect((): void => {
            if (afterDrop.isActive) throw new Error('efek samping gagal');
        }).not.toThrow();
        expect(afterDrop.isOverCanvas).toBe(false);
        expect(afterDrop.dropIndicatorIndex).toBe(-1);
    });

    it('regresi stuck state: leave setelah drop tidak menghidupkan marker', () => {
        const dropped = reduceAll(initialDragState, [
            PALETTE_STARTED,
            { type: 'canvasEnter' },
            { type: 'gapEnter', index: 2 },
            { type: 'drop' },
            { type: 'canvasLeave' },
            { type: 'canvasLeave' },
        ]);

        expect(dropped).toBe(initialDragState);
    });
});
