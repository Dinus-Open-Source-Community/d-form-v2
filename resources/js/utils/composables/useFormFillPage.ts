import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { normalizeBannerSrc, pickFormBannerField } from '@/components/modules/builder/formBanner'
import { readFieldMetadata, readFieldRules } from '@/lib/formFieldMetadata'
import type {
    FormAccessStatus,
    FormFillAnswerMap,
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
}) {
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

    const formBannerField = computed(() => pickFormBannerField(props.fields))

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
            not_visible: { title: 'You do not have access to this form.', body: fallback },
        }
        return map[props.accessStatus]
    })

    const initialValues: FormFillAnswerMap = {}
    for (const field of props.fields) {
        if (isDisplayOnly(field)) continue
        if (field.type === 'checkbox' || (field.type === 'select' && metadata(field).is_multiple)) {
            initialValues[field.name] = []
        } else if (field.type === 'fileUpload') {
            initialValues[field.name] = null
        } else {
            initialValues[field.name] = ''
        }
    }

    const answerForm = useForm<FormFillAnswerMap>(initialValues)

    function listFromCsv(value: unknown): string[] {
        return typeof value === 'string' ? value.split(',').map((item) => item.trim()).filter(Boolean) : []
    }

    function getOptions(field: IFormField): string[] {
        const direct = metadata(field).options
        if (typeof direct === 'string') return listFromCsv(direct)
        const ruleOptions = (rules(field).in as string | undefined)
        return listFromCsv(ruleOptions)
    }

    function getOptionRows(field: IFormField): FormFillOptionRow[] {
        const oc = metadata(field).optionChoices
        if (Array.isArray(oc)) {
            const rows: FormFillOptionRow[] = []
            for (const item of oc) {
                if (item && typeof item === 'object' && item !== null) {
                    const typedItem = item as { type?: string; label?: string; imageUrl?: string }
                    const type = (typedItem.type === 'image' ? 'image' : 'text') as 'text' | 'image'
                    const label = String(typedItem.label ?? '').trim()
                    const rawUrl = typeof typedItem.imageUrl === 'string' ? String(typedItem.imageUrl).trim() : ''

                    rows.push({
                        type,
                        label: type === 'text' ? label : (label || 'Image Choice'),
                        imageSrc: rawUrl ? normalizeBannerSrc(rawUrl) : undefined,
                    })
                }
            }
            if (rows.length > 0) return rows
        }
        const fallbackOptions = getOptions(field)
        return fallbackOptions.map((label) => ({ type: 'text', label }))
    }

    function getSelectedOptionRow(field: IFormField) {
        const val = answerForm[field.name] as string
        return getOptionRows(field).find((r) => r.label === val)
    }

    function getInputSubtype(field: IFormField): string {
        const type = metadata(field).type
        if (typeof type === 'string') {
            if (type === 'short_text') return 'text'
            if (type === 'phone') return 'tel'
            return type
        }
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
        return Boolean(metadata(field).is_multiple)
    }

    function isRadioLike(field: IFormField): boolean {
        const bt = builderType(field)
        return field.type === 'radio' || bt === 'radio'
    }

    function isCheckboxLike(field: IFormField): boolean {
        const bt = builderType(field)
        return field.type === 'checkbox' || bt === 'checkbox' || isMultipleSelect(field)
    }

    function fileHint(field: IFormField): string {
        const parts: string[] = []
        const fieldRules = rules(field)
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
        const current = Array.isArray(answerForm[fieldName]) ? (answerForm[fieldName] as string[]) : []
        answerForm[fieldName] = checked ? [...current, option] : current.filter((value) => value !== option)
    }

    function onFileChange(fieldName: string, event: Event) {
        const input = event.target as HTMLInputElement
        answerForm[fieldName] = input.files?.[0] ?? null
    }

    function submit() {
        if (isBlocked.value) return
        answerForm.post(props.submitUrl, {
            forceFormData: true,
        })
    }

    function fieldError(name: string): string | undefined {
        return (answerForm.errors as Record<string, string>)[name]
    }

    return {
        answerForm,
        metadata,
        builderType,
        formBannerImageSrc,
        formBannerCaption,
        formHasDescription,
        isBlocked,
        blockCopy,
        getOptionRows,
        getSelectedOptionRow,
        getInputSubtype,
        getPlaceholder,
        isRequired,
        isMultipleSelect,
        isRadioLike,
        isCheckboxLike,
        fileHint,
        acceptValue,
        onCheckboxToggle,
        onFileChange,
        submit,
        fieldError,
    }
}

export type FormFillPageContext = ReturnType<typeof useFormFillPage>
