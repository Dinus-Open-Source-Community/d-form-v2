import { computed, ref, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { getFieldError } from '@/lib/error-message';
import { uploadPercent } from '@/lib/fileDisplay';
import { routes } from '@/lib/routes';
import { ACCEPTED_IMAGE_EXTENSIONS, validateUploadFile } from '@/lib/uploadValidation';
import type { IProfileAvatar } from '@/types/profile';

/**
 * Kontrol avatar dengan unggah tertunda: berkas yang dipilih hanya dipratinjau
 * sampai `save()` dipanggil, sehingga satu klik simpan per bagian tidak memicu
 * request tambahan. `remove()` menandai avatar server untuk dihapus pada `save()`
 * berikutnya, dan `cancel()` membuang berkas tertunda beserta object URL-nya.
 *
 * `initialUrl` boleh berupa string biasa (nilai saat mount) atau getter/ref agar
 * tampilan mengikuti `auth.user.avatar` terbaru setelah simpan.
 */
export function useProfileAvatar(initialUrl: MaybeRefOrGetter<string | null>): IProfileAvatar {
    const { handleInertiaFormErrors, showInfoToast } = useToastNotifications();

    const pendingFile = ref<File | null>(null);
    const pendingPreviewUrl = ref<string | null>(null);
    const removalPending = ref<boolean>(false);
    const pickError = ref<string>('');
    const progress = ref<number | null>(null);

    const avatarUploadForm = useForm<{ avatar: File | null }>({ avatar: null });
    const avatarDeleteForm = useForm({});

    const previewUrl = computed<string | null>(() => {
        if (removalPending.value) return null;

        return pendingPreviewUrl.value ?? toValue(initialUrl);
    });

    const isPicked = computed<boolean>(() => pendingFile.value !== null);
    const isRemovalPending = computed<boolean>(() => removalPending.value);
    const isDirty = computed<boolean>(() => pendingFile.value !== null || removalPending.value);
    const isProcessing = computed<boolean>(
        () => avatarUploadForm.processing || avatarDeleteForm.processing
    );
    const errorMessage = computed<string>(
        () => pickError.value || getFieldError(avatarUploadForm.errors, 'avatar') || ''
    );

    function clearPending(): void {
        revokeObjectUrl(pendingPreviewUrl.value);
        pendingPreviewUrl.value = null;
        pendingFile.value = null;
        removalPending.value = false;
        pickError.value = '';
    }

    async function pick(file: File): Promise<void> {
        pickError.value = '';
        const validationError = await validateUploadFile(file, ACCEPTED_IMAGE_EXTENSIONS);

        if (validationError !== null) {
            pickError.value = validationError;
            return;
        }

        clearPending();
        pendingFile.value = file;
        pendingPreviewUrl.value = createObjectUrl(file);
    }

    function remove(): void {
        clearPending();
        removalPending.value = true;
    }

    function cancel(): void {
        clearPending();
    }

    function uploadPendingFile(file: File): void {
        avatarUploadForm.avatar = file;
        avatarUploadForm.post(routes.dashboard.profileAvatar, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                progress.value = 0;
            },
            onProgress: (event) => {
                if (event === undefined) return;

                progress.value = uploadPercent(event.loaded, event.total ?? 0);
            },
            onFinish: () => {
                progress.value = null;
            },
            onSuccess: () => {
                // Toast sukses berasal dari flash server (`ProfileController`).
                clearPending();
            },
            onError: (serverErrors) => {
                handleInertiaFormErrors({ ...serverErrors }, { title: 'Gagal menyimpan profil' });
            },
        });
    }

    function deleteAvatar(): void {
        avatarDeleteForm.delete(routes.dashboard.profileAvatar, {
            preserveScroll: true,
            onSuccess: () => {
                // Toast sukses berasal dari flash server (`ProfileController`).
                clearPending();
            },
            onError: (serverErrors) => {
                handleInertiaFormErrors({ ...serverErrors }, { title: 'Gagal menyimpan profil' });
            },
        });
    }

    function save(): void {
        if (isProcessing.value) return;

        const file = pendingFile.value;
        if (file !== null) {
            uploadPendingFile(file);
            return;
        }

        if (removalPending.value) {
            deleteAvatar();
            return;
        }

        showInfoToast('Tidak ada perubahan untuk disimpan.');
    }

    return {
        previewUrl,
        isPicked,
        isRemovalPending,
        isProcessing,
        isDirty,
        progress,
        errorMessage,
        pick,
        save,
        remove,
        cancel,
    };
}
