import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { useFormReferralCodes } from '@/hooks/useFormReferralCodes';
import type { IReferralPayload } from '@/types/referral';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}));

const routerPostMock = vi.mocked(router.post);

const payload: IReferralPayload = {
    enabled: true,
    codes: [],
    usage_url: '/answers',
};

function createState() {
    return useFormReferralCodes({
        eventId: 'event-id',
        formId: 'form-id',
        payload: () => payload,
        currentPath: () => '/forms/form-id',
    });
}

beforeEach(() => {
    routerPostMock.mockReset();
});

describe('useFormReferralCodes create', () => {
    it('posts the code input and clears processing when the visit finishes', () => {
        const state = createState();

        state.createCode({
            attribution_mode: 'name',
            attribution_name: 'Komunitas Udinus',
            prefix: 'UDIN',
            generation_mode: 'manual',
            body: 'AB12',
            is_active: true,
        });

        expect(state.processing.value).toBe(true);
        expect(routerPostMock).toHaveBeenCalledTimes(1);

        const [url, body, visitOptions] = routerPostMock.mock.calls[0] ?? [];
        expect(url).toContain('/referral-codes');
        expect(body).toMatchObject({ prefix: 'UDIN', body: 'AB12' });

        // onFinish Inertia menerima objek Visit penuh; hook hanya memakai momen selesainya.
        visitOptions?.onFinish?.({} as never);

        expect(state.processing.value).toBe(false);
    });

    it('stores validation errors from the server response', () => {
        const state = createState();

        state.createCode({
            attribution_mode: 'name',
            prefix: 'UDIN',
            generation_mode: 'random',
            is_active: true,
        });

        const visitOptions = routerPostMock.mock.calls[0]?.[2];
        visitOptions?.onError?.({ body: 'Kode sudah dipakai.' });

        expect(state.fieldErrors.value).toEqual({ body: 'Kode sudah dipakai.' });
        expect(state.errorMessage.value).toBe('');
    });
});
