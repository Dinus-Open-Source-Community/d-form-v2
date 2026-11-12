import { usePage } from '@inertiajs/vue3';
import type { CancelToken, Progress } from '@inertiajs/core';
import type { InertiaForm } from '@inertiajs/vue3';
import { ref, type ComputedRef, type Ref } from 'vue';
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
    /** Progres unggah multipart 0..100 dari visit submit yang sedang berjalan. */
    progress: Ref<number>;
    /** Hentikan visit submit yang berjalan; berkas yang sudah dipilih tetap di form. */
    cancel: () => void;
}

/**
 * Submits the fill form: blocks blocked forms, rejects member emails equal to the account email,
 * and forwards server validation errors to the shared Inertia error toast.
 * Upload progress and cancel come from the Inertia visit options (`onProgress`/`onCancelToken`),
 * so the multipart request stays the same one the form always used.
 */
export function useFormFillSubmit(options: IUseFormFillSubmitOptions): IUseFormFillSubmit {
    const { handleInertiaFormErrors, showErrorToast } = useToastNotifications();
    const progress = ref<number>(0);
    let cancelVisit: (() => void) | null = null;

    function submit(): void {
        if (options.isBlocked.value) return;

        const accountEmail = readAccountEmail();
        if (accountEmail && options.memberSlots > 0) {
            const emails = options.answerForm.team_member_emails;
            const memberEmails = Array.isArray(emails)
                ? emails.filter((email): email is string => typeof email === 'string')
                : [];
            if (containsAccountEmail(memberEmails, accountEmail)) {
                const message = 'Email anggota tidak boleh sama dengan email akun Anda. Gunakan email peserta lain.';
                options.answerForm.setError('team_member_emails', message);
                showErrorToast(message, { title: 'Email anggota tidak valid' });
                return;
            }
        }

        progress.value = 0;
        options.answerForm.post(options.submitUrl, {
            forceFormData: true,
            onProgress: (event: Progress | undefined): void => {
                progress.value = event?.percentage ?? 0;
            },
            onCancelToken: (token: CancelToken): void => {
                cancelVisit = token.cancel;
            },
            onFinish: (): void => {
                progress.value = 0;
                cancelVisit = null;
            },
            onError: (errors) => {
                handleInertiaFormErrors(errors, {
                    fields: [...options.fields],
                    fieldLabels: buildFieldLabelMap([...options.fields]),
                    title: 'Gagal mengirim pendaftaran',
                });
            },
        });
    }

    function cancel(): void {
        cancelVisit?.();
    }

    return { submit, progress, cancel };
}

/** Signed-in account email normalized for comparison; empty when unauthenticated. */
function readAccountEmail(): string {
    const email = usePage().props.auth?.user?.email;
    return typeof email === 'string' ? email.trim().toLowerCase() : '';
}
