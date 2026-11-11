import { usePage } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { buildFieldLabelMap } from '@/lib/error-message';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { containsAccountEmail } from '@/lib/formFillFields';
import type { TFormFillAnswerMap } from '@/types/form';

export interface IUseFormFillSubmitOptions {
    answerForm: InertiaForm<TFormFillAnswerMap>;
    fields: readonly IFormField[];
    submitUrl: string;
    memberSlots: number;
    isBlocked: ComputedRef<boolean>;
}

export interface IUseFormFillSubmit {
    submit: () => void;
}

/**
 * Submits the fill form: blocks blocked forms, rejects member emails equal to the account email,
 * and forwards server validation errors to the shared Inertia error toast.
 */
export function useFormFillSubmit(options: IUseFormFillSubmitOptions): IUseFormFillSubmit {
    const { handleInertiaFormErrors, showErrorToast } = useToastNotifications();

    function submit(): void {
        if (options.isBlocked.value) return;

        const accountEmail = readAccountEmail();
        if (accountEmail && options.memberSlots > 0) {
            const emails = options.answerForm.team_member_emails;
            const memberEmails = Array.isArray(emails) ? emails : [];
            if (containsAccountEmail(memberEmails, accountEmail)) {
                const message = 'Email anggota tidak boleh sama dengan email akun Anda. Gunakan email peserta lain.';
                options.answerForm.setError('team_member_emails', message);
                showErrorToast(message, { title: 'Email anggota tidak valid' });
                return;
            }
        }

        options.answerForm.post(options.submitUrl, {
            forceFormData: true,
            onError: (errors) => {
                handleInertiaFormErrors(errors, {
                    fields: [...options.fields],
                    fieldLabels: buildFieldLabelMap([...options.fields]),
                    title: 'Gagal mengirim pendaftaran',
                });
            },
        });
    }

    return { submit };
}

/** Signed-in account email normalized for comparison; empty when unauthenticated. */
function readAccountEmail(): string {
    const email = usePage().props.auth?.user?.email;
    return typeof email === 'string' ? email.trim().toLowerCase() : '';
}
