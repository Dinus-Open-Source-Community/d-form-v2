import { describe, expect, it, vi } from 'vitest';
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormFillSubmit } from '@/hooks/useFormFillSubmit';
import type { TFormFillAnswerMap } from '@/types/form';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();

    return {
        ...actual,
        usePage: () => ({ props: { auth: { user: { email: 'Me@Example.com ' } } } }),
    };
});

const toastMocks = vi.hoisted(() => ({
    showErrorToast: vi.fn(),
    handleInertiaFormErrors: vi.fn(),
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => toastMocks,
}));

const showErrorToastMock = toastMocks.showErrorToast;
const handleInertiaFormErrorsMock = toastMocks.handleInertiaFormErrors;

const FIELDS: IFormField[] = [
    { id: 'f1', type: 'input', label: 'Team member emails', name: 'team_member_emails', order: 0, metadata: {} },
];

function createSubmitter(options: { emails?: string[]; memberSlots?: number; blocked?: boolean } = {}) {
    const answerForm = useForm<TFormFillAnswerMap>({
        team_member_emails: options.emails ?? [],
    });
    const postSpy = vi.spyOn(answerForm, 'post').mockImplementation(() => {});

    const submitter = useFormFillSubmit({
        answerForm,
        fields: FIELDS,
        submitUrl: '/joined/events/1/forms/1/fill',
        memberSlots: options.memberSlots ?? 0,
        isBlocked: computed(() => options.blocked ?? false),
    });

    return { answerForm, submitter, postSpy };
}

describe('useFormFillSubmit', () => {
    it('does nothing when the form is blocked', () => {
        const { submitter, postSpy } = createSubmitter({
            blocked: true,
            emails: ['other@example.com'],
            memberSlots: 1,
        });

        submitter.submit();

        expect(postSpy).not.toHaveBeenCalled();
    });

    it('rejects member emails equal to the signed-in account email', () => {
        const { answerForm, submitter, postSpy } = createSubmitter({
            emails: ['me@example.com'],
            memberSlots: 1,
        });

        submitter.submit();

        expect(postSpy).not.toHaveBeenCalled();
        expect(answerForm.errors.team_member_emails).toBeTruthy();
        expect(showErrorToastMock).toHaveBeenCalledTimes(1);
    });

    it('submits as multipart form data when the answers are valid', () => {
        const { submitter, postSpy } = createSubmitter({ emails: ['other@example.com'], memberSlots: 1 });

        submitter.submit();

        expect(postSpy).toHaveBeenCalledTimes(1);
        const [url, options] = postSpy.mock.calls[0] ?? [];
        expect(url).toBe('/joined/events/1/forms/1/fill');
        expect(options?.forceFormData).toBe(true);
    });

    it('reports multipart upload progress and cancels only the running visit', () => {
        const { submitter, postSpy } = createSubmitter();

        submitter.submit();
        const options = postSpy.mock.calls[0]?.[1];
        const cancelVisit = vi.fn();
        options?.onCancelToken?.({ cancel: cancelVisit });
        options?.onProgress?.({ loaded: 512, total: 1024, progress: 0.5, percentage: 50 });

        expect(submitter.progress.value).toBe(50);

        submitter.cancel();
        expect(cancelVisit).toHaveBeenCalledTimes(1);
    });

    it('forwards server validation errors to the shared toast handler', () => {
        const { submitter, postSpy } = createSubmitter();

        submitter.submit();
        const options = postSpy.mock.calls[0]?.[1];
        options?.onError?.({ full_name: 'The full name field is required.' });

        expect(handleInertiaFormErrorsMock).toHaveBeenCalledTimes(1);
        expect(handleInertiaFormErrorsMock.mock.calls[0]?.[1]?.title).toBe('Gagal mengirim pendaftaran');
    });

    it('ignores the account-email guard when the form has no member slots', () => {
        const { submitter, postSpy } = createSubmitter({ emails: ['me@example.com'], memberSlots: 0 });

        submitter.submit();

        expect(postSpy).toHaveBeenCalledTimes(1);
    });
});
