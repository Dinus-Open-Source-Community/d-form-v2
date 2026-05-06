<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import FormBuilderWorkspace from '@/components/modules/builder/FormBuilderWorkspace.vue'
import { defaultFormBannerState, prependFormBannerToBackendPayload } from '@/components/modules/builder/formBanner'
import { toBackendFields } from '@/components/modules/builder/fieldMapping'
import type { BuilderField } from '@/types/form-builder'
import type { CreateDashboardFormPayload } from '@/types/form'

/** Inertia `FormDataType` cannot recurse `BackendField.metadata` (Record<string, unknown>); store fields loosely for typing only. */
type CreateFormClientPayload = Omit<CreateDashboardFormPayload, 'fields'> & {
    fields: object[]
}

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: { id: string; title: string }
}>()``

const formTitle = ref<string>('')
const formDescription = ref<string>('')
const closedAt = ref<string>('')
const visibleFor = ref<string[]>([])
const bannerState = reactive(defaultFormBannerState())
const formFields = ref<BuilderField[]>([])
const isSaving = ref<boolean>(false)

const createForm = useForm<CreateFormClientPayload>({
    title: '',
    description: '',
    closed_at: '',
    visible_for: [],
    banner_url: '',
    banner_caption: '',
    fields: [],
})

function onSave(): void {
    createForm.title = formTitle.value
    createForm.description = formDescription.value
    createForm.closed_at = closedAt.value
    createForm.visible_for = visibleFor.value
    createForm.banner_url = bannerState.bannerUrl
    createForm.banner_caption = bannerState.caption

    const merged = prependFormBannerToBackendPayload(formFields.value, bannerState)
    createForm.fields = toBackendFields(merged) as object[]

    isSaving.value = true
    createForm.post(`/dashboard/events/${props.event.id}/forms`, {
        forceFormData: true,
        onSuccess: () => toast.success('Form created successfully!'),
        onError: (err) => {
            isSaving.value = false
            const first = Object.values(err)[0]
            toast.error(typeof first === 'string' ? first : 'Failed to create form.')
        },
    })
}
</script>

<template>
    <Head title="Create Form" />

    <FormBuilderWorkspace
        v-model:form-title="formTitle"
        v-model:form-description="formDescription"
        v-model:closed-at="closedAt"
        v-model:visible-for="visibleFor"
        v-model:banner="bannerState"
        v-model:form-fields="formFields"
        :event="event"
        :toolbar-subtitle="`New form for ${event.title}`"
        save-label="Save Form"
        :processing="isSaving"
        @save="onSave"
    />
</template>
