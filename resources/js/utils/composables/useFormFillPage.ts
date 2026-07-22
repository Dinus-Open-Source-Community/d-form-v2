import { computed, onBeforeUnmount, reactive } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { normalizeBannerSrc, pickFormBannerField } from '@/components/modules/builder/formBanner'
import { isCheckboxOptionSelected, toggleCheckboxSelection } from '@/lib/formCheckboxAnswers'
import {
    buildFieldLabelMap,
    getFieldError,
    handleInertiaFormErrors,
    showErrorToast,
    type ErrorMessageContext,
} from '@/lib/error-message'
import { getFormFieldOptionRows } from '@/lib/formFieldOptions'
import { readFieldMetadata, readFieldRules, readMetaBoolean } from '@/lib/formFieldMetadata'
import type {
    FormAccessStatus,
    FormFillOptionRow,
    FormFillPageEvent,
    FormFillPageForm,
    FormFieldMetadataBag,
    FormFieldRules,
} from '@/types/form'

export function useFormFillPage(props: {
    event: FormFillPageEvent
    form: FormFillPageForm
    fields: IFormField[]
    submitUrl: string
    accessStatus: FormAccessStatus
    accessMessage: string
    memberSlots: number
    registrationMode: string
}) {
    const fields = props.fields ?? []
    const page = usePage()

    function metadata(field: IFormField): FormFieldMetadataBag {
        return readFieldMetadata(field)
    }

    function rules(field: IFormField): FormFieldRules {
        return readFieldRules(field)
    }

    function builderType(field: IFormField): string {
        const value = metadata(field).builderType
        if (typeof value === 'string') return value
        if (field.name === 'form_banner' || field.type === 'banner') return 'banner'
        return field.type
    }

    function isDisplayOnly(field: IFormField): boolean {
        const bt = builderType(field)
        return ['heading', 'paragraph', 'divider', 'banner'].includes(bt)
    }

    const formBannerField = computed(() => pickFormBannerField(props.fields ?? []))

    const formBannerImageSrc = computed(() => {
        const fb = formBannerField.value
        const meta = fb ? metadata(fb) : {}
        const url = (meta.bannerUrl as string) || props.form.banner_url
        if (!url) return ''
        return normalizeBannerSrc(url)
    })

    const formBannerCaption = computed(() => {
        const fb = formBannerField.value
        if (fb) {
            const raw = metadata(fb).content
            if (typeof raw === 'string' && raw.trim()) return raw
        }
        return props.form.banner_caption ?? ''
    })

    const formHasDescription = computed(() => Boolean(props.form.description?.trim()))
    const isBlocked = computed(() => props.accessStatus !== 'allowed')

    const blockCopy = computed(() => {
        const fallback = props.accessMessage || 'This form is not available right now.'
        const map: Record<FormAccessStatus, { title: string; body: string; success?: boolean }> = {
            allowed: { title: '', body: '' },
            already_submitted: { title: 'You have already submitted this form.', body: fallback, success: true },
            form_closed: { title: 'Registration is closed.', body: fallback },
            registration_not_open: { title: 'Registration is not currently open.', body: fallback },
            quota_full: { title: 'Registration is full.', body: fallback },
            unsupported_registration_mode: {
                title: 'This registration type is not available yet.',
                body: fallback,
            },
            pending_team_confirmation: {
                title: 'Confirm your team invitation',
                body: fallback,
            },
            invitation_closed: {
                title: 'Invitation no longer active',
                body: fallback,
            },
            event_form_already_chosen: {
                title: 'Another registration form was already chosen for this event.',
                body: fallback,
            },
            prerequisite_not_met: {
                title: 'Required form not completed yet.',
                body: fallback,
            },
            not_visible: { title: 'You do not have access to this form.', body: fallback },
        }
        return map[props.accessStatus]
    })

    const initialValues: Record<string, FormFillAnswerValue | string[]> = {}
    for (const field of fields) {
        if (isDisplayOnly(field)) continue
        if (field.type === 'checkbox' || (field.type === 'select' && readMetaBoolean(metadata(field), 'is_multiple'))) {
            initialValues[field.name] = []
        } else if (field.type === 'fileUpload') {
            initialValues[field.name] = null
        } else {
            initialValues[field.name] = ''
        }
    }

    if (props.memberSlots > 0) {
        initialValues.team_member_emails = Array.from({ length: props.memberSlots }, () => '')
    }

    if (props.registrationMode === 'bundle' && props.memberSlots > 0) {
        for (const field of fields) {
            if (isDisplayOnly(field)) continue
            if (metadata(field).duplicatable !== true) continue
            for (let i = 0; i < props.memberSlots; i++) {
                const key = `bundle__${field.name}__${i}`
                if (field.type === 'checkbox' || (field.type === 'select' && readMetaBoolean(metadata(field), 'is_multiple'))) {
                    initialValues[key] = []
                } else if (field.type === 'fileUpload') {
                    initialValues[key] = null
                } else {
                    initialValues[key] = ''
                }
            }
        }
    }

    const answerForm = useForm(initialValues as Record<string, unknown>)

    const errorContext = computed<ErrorMessageContext>(() => ({
        fields,
        fieldLabels: buildFieldLabelMap(fields),
    }))

    /** Object URLs for local file previews (image upload); revoked on clear/unmount. */
    const filePreviewUrls = reactive<Record<string, string>>({})

    function revokeFilePreview(fieldName: string): void {
        const url = filePreviewUrls[fieldName]
        if (url) {
            URL.revokeObjectURL(url)
            delete filePreviewUrls[fieldName]
        }
    }

    function getOptionRows(field: IFormField): FormFillOptionRow[] {
        return getFormFieldOptionRows(field)
    }

    function getSelectedOptionRow(field: IFormField, storageKey?: string) {
        const key = storageKey ?? field.name
        const val = answerForm[key] as string
        return getOptionRows(field).find((r) => r.label === val)
    }

    function isBundleDuplicatableField(field: IFormField): boolean {
        return props.registrationMode === 'bundle' && metadata(field).duplicatable === true && !isDisplayOnly(field)
    }

    function participationSlotsForField(field: IFormField): { slotIndex: number | null; title: string }[] {
        if (isBundleDuplicatableField(field)) {
            const rows: { slotIndex: number | null; title: string }[] = [{ slotIndex: null, title: 'Lead participant' }]
            for (let i = 0; i < props.memberSlots; i++) {
                rows.push({ slotIndex: i, title: `Participant ${i + 2}` })
            }
            return rows
        }
        return [{ slotIndex: null, title: '' }]
    }

    function answerKeyForSlot(field: IFormField, slotIndex: number | null): string {
        if (slotIndex === null) return field.name
        return `bundle__${field.name}__${slotIndex}`
    }

    function getInputSubtype(field: IFormField): string {
        const type = metadata(field).type
        if (typeof type === 'string') {
            if (type === 'short_text') return 'text'
            if (type === 'phone') return 'tel'
            return type
        }
        const bt = builderType(field)
        if (bt === 'url') return 'url'
        return 'text'
    }

    function getPlaceholder(field: IFormField): string {
        const placeholder = metadata(field).placeholder
        return typeof placeholder === 'string' ? placeholder : ''
    }

    function isRequired(field: IFormField): boolean {
        return Boolean(rules(field).required)
    }

    function isMultipleSelect(field: IFormField): boolean {
        return readMetaBoolean(metadata(field), 'is_multiple')
    }

    function isRadioLike(field: IFormField): boolean {
        const bt = builderType(field)
        return field.type === 'radio' || bt === 'radio' || bt === 'yes_no'
    }

    function isCheckboxLike(field: IFormField): boolean {
        const bt = builderType(field)
        return field.type === 'checkbox' || bt === 'checkbox' || isMultipleSelect(field)
    }

    /** Batas karakter untuk teks pendek / panjang (metadata.rules + maxLength). */
    function maxLengthForField(field: IFormField): number | undefined {
        const bt = builderType(field)
        if (bt !== 'short_text' && bt !== 'long_text') return undefined
        const r = rules(field)
        const rm = r.max
        if (typeof rm === 'number' && rm > 0) return rm
        if (typeof rm === 'string' && rm !== '') {
            const n = parseInt(rm, 10)
            if (Number.isFinite(n) && n > 0) return n
        }
        const meta = metadata(field)
        const ml = meta.maxLength as unknown
        if (typeof ml === 'number' && ml > 0) return ml
        if (typeof ml === 'string' && ml.trim() !== '') {
            const n = parseInt(ml, 10)
            if (Number.isFinite(n) && n > 0) return n
        }
        return undefined
    }

    function fileHint(field: IFormField): string {
        const parts: string[] = []
        const fieldRules = rules(field)
        if (builderType(field) === 'image_upload') parts.push('Recommended: 1200 x 900 px (4:3)')
        if (fieldRules.mimes) parts.push(`Allowed: ${String(fieldRules.mimes)}`)
        if (fieldRules.max_size) parts.push(`Max size: ${String(fieldRules.max_size)} KB`)
        return parts.join(' · ')
    }

    function acceptValue(field: IFormField): string | undefined {
        const mimes = rules(field).mimes
        if (typeof mimes !== 'string') return undefined
        return mimes.split(',').map((ext) => `.${ext.trim().replace(/^\./, '')}`).join(',')
    }

    function onCheckboxToggle(fieldName: string, option: string, checked: boolean) {
        answerForm[fieldName] = toggleCheckboxSelection(answerForm[fieldName], option, checked)
    }

    function isCheckboxSelected(fieldName: string, option: string): boolean {
        return isCheckboxOptionSelected(answerForm[fieldName], option)
    }

    function onFileChange(fieldName: string, event: Event) {
        const input = event.target as HTMLInputElement
        const file = input.files?.[0] ?? null
        revokeFilePreview(fieldName)
        if (file) {
            filePreviewUrls[fieldName] = URL.createObjectURL(file)
        }
        answerForm[fieldName] = file
    }

    function clearFileUpload(fieldName: string) {
        revokeFilePreview(fieldName)
        answerForm[fieldName] = null
    }

    onBeforeUnmount(() => {
        for (const key of Object.keys(filePreviewUrls)) {
            revokeFilePreview(key)
        }
    })

    function submit() {
        if (isBlocked.value) return

        const myEmail = (page.props as Props).auth?.user?.email?.trim().toLowerCase()
        if (myEmail && props.memberSlots > 0) {
            const emails = (answerForm.team_member_emails as string[] | undefined) ?? []
            const clash = emails.some((e) => (e?.trim().toLowerCase() ?? '') === myEmail)
            if (clash) {
                const message =
                    'Email anggota tidak boleh sama dengan email akun Anda. Gunakan email peserta lain.'
                answerForm.setError('team_member_emails', message)
                showErrorToast(message, { title: 'Email anggota tidak valid' })
                return
            }
        }

        answerForm.post(props.submitUrl, {
            forceFormData: true,
            onError: (errors) => {
                handleInertiaFormErrors(errors, {
                    ...errorContext.value,
                    title: 'Gagal mengirim pendaftaran',
                })
            },
        })
    }

    function fieldError(name: string): string | undefined {
        return getFieldError(answerForm.errors, name, errorContext.value)
    }

    function fieldKeysForField(field: IFormField): string[] {
        return participationSlotsForField(field).map((slot) => answerKeyForSlot(field, slot.slotIndex))
    }

    function cardErrorsForFields(fields: IFormField[]): string[] {
        const messages = new Set<string>()
        for (const field of fields) {
            for (const key of fieldKeysForField(field)) {
                const message = fieldError(key)
                if (message) messages.add(message)
            }
        }
        return [...messages]
    }

    function cardErrorsForBundleSlot(fields: IFormField[], slotIndex: number | null): string[] {
        const messages = new Set<string>()
        for (const field of fields) {
            const message = fieldError(answerKeyForSlot(field, slotIndex))
            if (message) messages.add(message)
        }
        return [...messages]
    }

    const memberSlots = computed(() => props.memberSlots)

    return {
        answerForm,
        metadata,
        builderType,
        registrationMode: props.registrationMode,
        formBannerImageSrc,
        formBannerCaption,
        formHasDescription,
        isBlocked,
        blockCopy,
        memberSlots,
        getOptionRows,
        getSelectedOptionRow,
        isBundleDuplicatableField,
        participationSlotsForField,
        answerKeyForSlot,
        getInputSubtype,
        getPlaceholder,
        isRequired,
        isMultipleSelect,
        isRadioLike,
        isCheckboxLike,
        maxLengthForField,
        fileHint,
        acceptValue,
        onCheckboxToggle,
        isCheckboxSelected,
        onFileChange,
        clearFileUpload,
        filePreviewUrls,
        submit,
        fieldError,
        cardErrorsForFields,
        cardErrorsForBundleSlot,
    }
}

export type FormFillPageContext = ReturnType<typeof useFormFillPage>
