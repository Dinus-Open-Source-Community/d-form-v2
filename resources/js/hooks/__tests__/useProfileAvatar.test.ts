import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { routes } from '@/lib/routes';
import { useProfileAvatar } from '@/hooks/useProfileAvatar';

const inertiaMock = vi.hoisted(() => ({
    useForm: vi.fn(),
}));

const toastMock = vi.hoisted(() => ({
    handleInertiaFormErrors: vi.fn(),
    showInfoToast: vi.fn(),
    showSuccessToast: vi.fn(),
}));

const objectUrlMock = vi.hoisted(() => ({
    createObjectUrl: vi.fn(),
    revokeObjectUrl: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: inertiaMock.useForm,
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => toastMock,
}));

vi.mock('@/adapters/browser/objectUrl', () => ({
    createObjectUrl: objectUrlMock.createObjectUrl,
    revokeObjectUrl: objectUrlMock.revokeObjectUrl,
}));

interface IFakeUploadForm {
    avatar: File | null;
    processing: boolean;
    errors: Record<string, string>;
    post: ReturnType<typeof vi.fn>;
}

interface IFakeDeleteForm {
    processing: boolean;
    errors: Record<string, string>;
    delete: ReturnType<typeof vi.fn>;
}

interface IAvatarHarness {
    upload: IFakeUploadForm;
    removeRequest: IFakeDeleteForm;
}

function createFakeForms(): IAvatarHarness {
    return {
        upload: { avatar: null, processing: false, errors: {}, post: vi.fn() },
        removeRequest: { processing: false, errors: {}, delete: vi.fn() },
    };
}

function mountAvatar(initialUrl: string | null): IAvatarHarness & {
    avatar: ReturnType<typeof useProfileAvatar>;
} {
    const harness = createFakeForms();
    inertiaMock.useForm.mockReturnValueOnce(reactive(harness.upload)).mockReturnValueOnce(reactive(harness.removeRequest));

    const avatar = useProfileAvatar(initialUrl);

    return { ...harness, avatar };
}

/** Berkas PNG kecil dengan signature valid agar lolos `validateUploadFile`. */
function createPngFile(name = 'foto.png'): File {
    const header = new Uint8Array(12);
    header.set([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

    return new File([header], name, { type: 'image/png' });
}

beforeEach(() => {
    vi.stubGlobal(
        'createImageBitmap',
        vi.fn(async () => ({ close: (): void => undefined }))
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('useProfileAvatar', () => {
    it('rejects a file above 5120 KB without creating a preview or request', async () => {
        const { avatar, upload, removeRequest } = mountAvatar(null);
        const oversize = new File([new Uint8Array(5121 * 1024)], 'besar.png', { type: 'image/png' });

        await avatar.pick(oversize);
        avatar.save();

        expect(avatar.errorMessage.value).not.toBe('');
        expect(avatar.previewUrl.value).toBeNull();
        expect(avatar.isPicked.value).toBe(false);
        expect(avatar.isDirty.value).toBe(false);
        expect(objectUrlMock.createObjectUrl).not.toHaveBeenCalled();
        expect(upload.post).not.toHaveBeenCalled();
        expect(removeRequest.delete).not.toHaveBeenCalled();
    });

    it('creates a preview URL for a valid pick and revokes it on cancel', async () => {
        objectUrlMock.createObjectUrl.mockReturnValue('blob:foto-baru');
        const { avatar } = mountAvatar('/storage/avatar-lama.png');

        await avatar.pick(createPngFile());

        expect(avatar.previewUrl.value).toBe('blob:foto-baru');
        expect(avatar.isPicked.value).toBe(true);
        expect(avatar.isDirty.value).toBe(true);

        avatar.cancel();

        expect(objectUrlMock.revokeObjectUrl).toHaveBeenCalledWith('blob:foto-baru');
        expect(avatar.previewUrl.value).toBe('/storage/avatar-lama.png');
        expect(avatar.isDirty.value).toBe(false);
    });

    it('uploads the picked file with multipart progress on save', async () => {
        objectUrlMock.createObjectUrl.mockReturnValue('blob:foto-baru');
        const { avatar, upload } = mountAvatar(null);
        await avatar.pick(createPngFile());

        avatar.save();

        expect(upload.post).toHaveBeenCalledTimes(1);
        expect(upload.post).toHaveBeenCalledWith(
            routes.dashboard.profileAvatar,
            expect.objectContaining({ forceFormData: true, preserveScroll: true })
        );

        const options = upload.post.mock.calls[0]?.[1];
        options?.onProgress?.({ loaded: 512, total: 1024 });
        expect(avatar.progress.value).toBe(50);

        options?.onFinish?.();
        expect(avatar.progress.value).toBeNull();
    });

    it('sends a delete request when the existing photo is marked for removal', () => {
        const { avatar, removeRequest } = mountAvatar('/storage/avatar-lama.png');

        avatar.remove();
        expect(avatar.previewUrl.value).toBeNull();
        expect(avatar.isDirty.value).toBe(true);

        avatar.save();

        expect(removeRequest.delete).toHaveBeenCalledWith(
            routes.dashboard.profileAvatar,
            expect.objectContaining({ preserveScroll: true })
        );
    });
});
