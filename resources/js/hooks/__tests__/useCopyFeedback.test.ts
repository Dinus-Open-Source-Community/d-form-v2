import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import type { EffectScope } from 'vue';
import { COPY_FEEDBACK_DURATION_MS, useCopyFeedback, type ICopyFeedback } from '@/hooks/useCopyFeedback';

describe('useCopyFeedback', () => {
    let scope: EffectScope;
    let feedback: ICopyFeedback;

    beforeEach((): void => {
        vi.useFakeTimers();
        const running = effectScope();
        scope = running;
        feedback = running.run((): ICopyFeedback => useCopyFeedback()) as ICopyFeedback;
    });

    afterEach((): void => {
        scope.stop();
        vi.useRealTimers();
    });

    it('mulai dari idle', (): void => {
        expect(feedback.active.value).toBe(false);
    });

    it('aktif segera setelah trigger dan kembali idle tepat setelah durasi', (): void => {
        feedback.trigger();
        expect(feedback.active.value).toBe(true);

        vi.advanceTimersByTime(COPY_FEEDBACK_DURATION_MS - 1);
        expect(feedback.active.value).toBe(true);

        vi.advanceTimersByTime(1);
        expect(feedback.active.value).toBe(false);
    });

    it('trigger berulang me-reset timer, bukan menumpuk', (): void => {
        feedback.trigger();
        vi.advanceTimersByTime(600);
        feedback.trigger();
        vi.advanceTimersByTime(600);

        // Total 1200 ms, tetapi timer terakhir baru berjalan 600 ms.
        expect(feedback.active.value).toBe(true);

        vi.advanceTimersByTime(400);
        expect(feedback.active.value).toBe(false);
    });

    it('membersihkan timer saat scope berhenti', (): void => {
        feedback.trigger();
        scope.stop();
        vi.advanceTimersByTime(COPY_FEEDBACK_DURATION_MS);

        expect(feedback.active.value).toBe(false);
        expect(vi.getTimerCount()).toBe(0);
    });
});
