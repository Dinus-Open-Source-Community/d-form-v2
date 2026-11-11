import { describe, expect, it } from 'vitest';
import {
    buildFormBannerBuilderField,
    defaultFormBannerState,
    type IFormBannerState,
} from '@/components/modules/builder/formBanner';

function bannerState(partial: Partial<IFormBannerState> = {}): IFormBannerState {
    return { ...defaultFormBannerState(), ...partial };
}

describe('buildFormBannerBuilderField', () => {
    it('membuang URL preview lokal (`blob:`/`data:`) dari metadata banner', () => {
        const blobBanner = buildFormBannerBuilderField(bannerState({ bannerUrl: 'blob:https://dform.test/1' }));
        const dataBanner = buildFormBannerBuilderField(
            bannerState({ bannerUrl: 'data:image/png;base64,AAAA', caption: 'Caption' })
        );

        expect(blobBanner?.metadata.bannerUrl).toBe('');
        expect(dataBanner?.metadata.bannerUrl).toBe('');
        expect(dataBanner?.metadata.content).toBe('Caption');
    });

    it('mempertahankan URL server yang valid (di-trim)', () => {
        const banner = buildFormBannerBuilderField(
            bannerState({ bannerUrl: ' https://dform.test/storage/banner.png ' })
        );

        expect(banner?.metadata.bannerUrl).toBe('https://dform.test/storage/banner.png');
    });

    it('tidak membuat field bila tidak ada URL valid maupun caption', () => {
        expect(buildFormBannerBuilderField(defaultFormBannerState())).toBeNull();
    });

    it('caption-only tetap membuat field dengan bannerUrl kosong', () => {
        const banner = buildFormBannerBuilderField(bannerState({ caption: 'Hanya caption' }));

        expect(banner?.metadata.bannerUrl).toBe('');
        expect(banner?.metadata.formBanner).toBe(true);
    });
});
