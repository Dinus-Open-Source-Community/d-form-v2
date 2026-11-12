import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { copyTextToClipboard } from '@/adapters/browser/clipboard';
import { updateFormShareLink } from '@/adapters/http/formShare';
import type { IFormSharePayload } from '@/types/formShare';
import { useFormShareActions } from '@/hooks/useFormShareActions';

vi.mock('@/adapters/browser/clipboard', () => ({ copyTextToClipboard: vi.fn() }));
vi.mock('@/adapters/http/formShare', () => ({ updateFormShareLink: vi.fn() }));

const copyMock = vi.mocked(copyTextToClipboard);
const updateMock = vi.mocked(updateFormShareLink);

function makePayload(canShareParticipant: boolean = true): IFormSharePayload {
    return {
        participant_path: '/joined/event-a/forms/form-a/fill',
        admin_path: '/admin/events/event-a/forms/form-a?tab=editor',
        short_path: '/s/form-a',
        barcode_path: '/admin/events/event-a/forms/form-a/share-link/barcode',
        update_url: '/admin/events/event-a/forms/form-a/share-link',
        alias: 'form-a',
        can_share_participant: canShareParticipant,
    };
}

describe('useFormShareActions', () => {
    beforeEach(() => {
        copyMock.mockReset();
        updateMock.mockReset();
        vi.stubGlobal('window', { location: { origin: 'https://forms.example.test' } });
    });

    it('builds absolute same-origin URLs and copies participant and admin links independently', async () => {
        copyMock.mockResolvedValue(true);
        const state = useFormShareActions(makePayload());

        await expect(state.copyParticipantLink()).resolves.toBe(true);
        expect(copyMock).toHaveBeenLastCalledWith('https://forms.example.test/joined/event-a/forms/form-a/fill');
        expect(state.copyStatus.value).toBe('participant');

        await expect(state.copyAdminLink()).resolves.toBe(true);
        expect(copyMock).toHaveBeenLastCalledWith(
            'https://forms.example.test/admin/events/event-a/forms/form-a?tab=editor'
        );
        expect(state.copyStatus.value).toBe('admin');

        await expect(state.copyShortLink()).resolves.toBe(true);
        expect(copyMock).toHaveBeenLastCalledWith('https://forms.example.test/s/form-a');
        expect(state.copyStatus.value).toBe('short');
    });

    it('keeps clipboard failures visible', async () => {
        copyMock.mockResolvedValue(false);
        const state = useFormShareActions(makePayload());

        await expect(state.copyParticipantLink()).resolves.toBe(false);
        expect(state.copyStatus.value).toBe('error');
        expect(state.errorMessage.value).toContain('clipboard');
    });

    it('saves the optional alias and adopts the refreshed payload', async () => {
        const refreshed = { ...makePayload(), alias: 'new-name', short_path: '/s/new-name' };
        updateMock.mockResolvedValue(refreshed);
        const state = useFormShareActions(makePayload());
        state.alias.value = 'new-name';

        await expect(state.saveAlias()).resolves.toBe(true);
        expect(updateMock).toHaveBeenCalledWith({
            url: '/admin/events/event-a/forms/form-a/share-link',
            alias: 'new-name',
        });
        expect(state.payload.value).toEqual(refreshed);
        expect(state.alias.value).toBe('new-name');
        expect(state.mutationStatus.value).toBe('success');
    });

    it('reports alias save errors without discarding the draft value', async () => {
        updateMock.mockRejectedValue(new Error('Alias sudah digunakan.'));
        const state = useFormShareActions(makePayload());
        state.alias.value = 'taken';

        await expect(state.saveAlias()).resolves.toBe(false);
        expect(state.alias.value).toBe('taken');
        expect(state.mutationStatus.value).toBe('error');
        expect(state.errorMessage.value).toBe('');
        expect(state.aliasErrorMessage.value).toBe('Alias sudah digunakan.');
    });

    it('shows and hides the barcode preview', () => {
        const state = useFormShareActions(makePayload());

        state.showBarcode();
        expect(state.barcodeVisible.value).toBe(true);
        expect(state.barcodeStatus.value).toBe('loading');
        state.markBarcodeLoaded();
        expect(state.barcodeStatus.value).toBe('loaded');
        state.hideBarcode();
        expect(state.barcodeVisible.value).toBe(false);
        expect(state.barcodeStatus.value).toBe('idle');
    });

    it('exposes barcode load failure and allows retry', () => {
        const state = useFormShareActions(makePayload());

        state.showBarcode();
        state.markBarcodeFailed();
        expect(state.barcodeStatus.value).toBe('error');

        state.retryBarcode();
        expect(state.barcodeAttempt.value).toBe(1);
        expect(state.barcodeStatus.value).toBe('loading');
    });

    it('clears a loaded barcode when participant sharing becomes unavailable', async () => {
        const state = useFormShareActions(makePayload());
        state.showBarcode();
        state.markBarcodeLoaded();

        state.payload.value.can_share_participant = false;
        await nextTick();

        expect(state.barcodeVisible.value).toBe(false);
        expect(state.barcodeStatus.value).toBe('idle');
    });

    it('blocks participant, short-link, alias and barcode actions for drafts but keeps admin copy available', async () => {
        const state = useFormShareActions(makePayload(false));

        await expect(state.copyParticipantLink()).resolves.toBe(false);
        await expect(state.copyShortLink()).resolves.toBe(false);
        await expect(state.saveAlias()).resolves.toBe(false);
        state.showBarcode();

        expect(state.barcodeVisible.value).toBe(false);
        expect(state.draftExplanation.value).toContain('Terbitkan');
        expect(copyMock).not.toHaveBeenCalled();
        expect(updateMock).not.toHaveBeenCalled();
    });
});
