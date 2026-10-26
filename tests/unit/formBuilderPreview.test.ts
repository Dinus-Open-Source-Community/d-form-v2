import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    FORM_BUILDER_LIVE_PREVIEW_MIN_WIDTH,
    shouldShowLivePreview,
} from '@/lib/formBuilderPreview';

describe('shouldShowLivePreview', () => {
    it('menolak lebar satu piksel di bawah ambang', () => {
        assert.equal(shouldShowLivePreview(999), false);
    });

    it('menerima tepat di ambang', () => {
        assert.equal(shouldShowLivePreview(1000), true);
    });

    it('menerima lebar satu piksel di atas ambang', () => {
        assert.equal(shouldShowLivePreview(1001), true);
    });

    it('ambang sama dengan konstanta yang dipakai builder', () => {
        assert.equal(shouldShowLivePreview(FORM_BUILDER_LIVE_PREVIEW_MIN_WIDTH), true);
        assert.equal(shouldShowLivePreview(FORM_BUILDER_LIVE_PREVIEW_MIN_WIDTH - 1), false);
    });
});
