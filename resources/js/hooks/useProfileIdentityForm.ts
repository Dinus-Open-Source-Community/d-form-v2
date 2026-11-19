import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { getFieldError, type TValidationErrors } from '@/lib/error-message';
import { routes } from '@/lib/routes';
import type { IProfileIdentityForm, IProfileIdentityFormValues } from '@/types/profile';

/**
 * Form identitas (nama + email) dengan simpan sendiri. `isDirty` dibandingkan
 * dengan nilai yang terakhir tersimpan, dan `errors` sudah di-humanize agar
 * panel cukup menampilkannya tanpa memproses pesan server lagi.
 */
export function useProfileIdentityForm(user: IUser): IProfileIdentityForm {
    const { handleInertiaFormErrors, showInfoToast } = useToastNotifications();

    const committed = ref<IProfileIdentityFormValues>({ name: user.name, email: user.email });
    const form = useForm<IProfileIdentityFormValues>({ name: user.name, email: user.email });

    const isDirty = computed<boolean>(
        () => form.name !== committed.value.name || form.email !== committed.value.email
    );

    const errors = computed<Record<string, string>>(() => {
        const bag: TValidationErrors = { name: form.errors.name, email: form.errors.email };
        const displayed: Record<string, string> = {};
        const nameError = getFieldError(bag, 'name');
        const emailError = getFieldError(bag, 'email');
        if (nameError !== undefined) displayed.name = nameError;
        if (emailError !== undefined) displayed.email = emailError;

        return displayed;
    });

    function submit(): void {
        if (form.processing) return;

        if (!isDirty.value) {
            showInfoToast('Tidak ada perubahan untuk disimpan.');
            return;
        }

        form.patch(routes.dashboard.profile, {
            preserveScroll: true,
            onSuccess: () => {
                committed.value = { name: form.name, email: form.email };
                form.defaults({ name: form.name, email: form.email });
                // Toast sukses berasal dari flash server (`ProfileController@update`).
            },
            onError: (serverErrors) => {
                handleInertiaFormErrors(serverErrors, { title: 'Gagal menyimpan profil' });
            },
        });
    }

    return { form, isDirty, errors, submit };
}
