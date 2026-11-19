import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { passwordSubmitBlockerMessage } from '@/lib/passwordStrength';
import { routes } from '@/lib/routes';
import type {
    IProfilePasswordForm,
    IProfilePasswordFormValues,
    IProfilePasswordVisibility,
    TProfilePasswordField,
} from '@/types/profile';

/**
 * Form kata sandi dengan simpan sendiri. `current_password` hanya dikirim bila
 * akun memang punya kata sandi lokal, karena aturan server mewajibkannya hanya
 * pada kondisi itu. Field tidak diingat agar tidak tersimpan di state history.
 *
 * Tiap field punya status visibilitas sendiri supaya tombol mata di dalam input
 * hanya membuka field yang ditekan. Guard sebelum request memakai aturan dan
 * pesan yang sama dengan halaman daftar.
 */
export function useProfilePasswordForm(user: IUser): IProfilePasswordForm {
    const { handleInertiaFormErrors, showErrorToast, showInfoToast, showSuccessToast } = useToastNotifications();

    const form = useForm<IProfilePasswordFormValues>({
        current_password: '',
        password: '',
        password_confirmation: '',
    }).dontRemember('current_password', 'password', 'password_confirmation');

    const hasLocalPassword = computed<boolean>(() => user.has_local_password !== false);

    const visibility: IProfilePasswordVisibility = {
        current: ref<boolean>(false),
        next: ref<boolean>(false),
        confirm: ref<boolean>(false),
    };

    const isDirty = computed<boolean>(
        () => form.current_password !== '' || form.password !== '' || form.password_confirmation !== ''
    );

    function toggleVisibility(field: TProfilePasswordField): void {
        visibility[field].value = !visibility[field].value;
    }

    function submit(): void {
        if (form.processing) return;

        if (!isDirty.value) {
            showInfoToast('Tidak ada perubahan untuk disimpan.');
            return;
        }

        const blockerMessage = passwordSubmitBlockerMessage(form.password, form.password_confirmation);
        if (blockerMessage !== null) {
            showErrorToast(blockerMessage);
            return;
        }

        form.transform((values: IProfilePasswordFormValues): object =>
            hasLocalPassword.value
                ? values
                : { password: values.password, password_confirmation: values.password_confirmation }
        );

        form.put(routes.dashboard.profilePassword, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                showSuccessToast('Perubahan berhasil disimpan.');
            },
            onError: (serverErrors) => {
                handleInertiaFormErrors(serverErrors, { title: 'Gagal menyimpan profil' });
            },
        });
    }

    return { form, isDirty, hasLocalPassword, visibility, toggleVisibility, submit };
}
