<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import FormBuilderWorkspace from '@/components/modules/builder/FormBuilderWorkspace.vue'
import {
    fromBackendField,
    toBackendFields,
    type BackendField,
    type BuilderField,
} from '@/components/modules/builder/fieldMapping'
import {
    defaultFormBannerState,
    prependFormBannerToBackendPayload,
    extractFormBannerFromBuilderFields,
} from '@/components/modules/builder/formBanner'
import { Button } from '@/components/ui/button'
import { emptyFormRegistrationMetadata, parseFormRegistrationMetadata, toFormMetadataPayload } from '@/types/form'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: { id: string; title: string }
    form: IForm
    fields: BackendField[]
    saveFieldsUrl: string
    updateFormUrl: string
}>()

const settingsForm = useForm({
    _method: 'put',
    title: props.form.title,
    description: props.form.description,
    closed_at: props.form.closed_at ?? '',
    visible_for: [...props.form.visible_for],
    banner_url: props.form.banner_url ?? '',
    banner_caption: props.form.banner_caption ?? '',
})

const bannerState = reactive(defaultFormBannerState())
const formFields = ref<BuilderField[]>([])
const formMetadata = reactive(emptyFormRegistrationMetadata())

const formTitle = computed({
    get: () => settingsForm.title,
    set: (v: string) => {
        settingsForm.title = v
    },
})
const formDescription = computed({
    get: () => settingsForm.description,
    set: (v: string) => {
        settingsForm.description = v
    },
})
const closedAt = computed({
    get: () => settingsForm.closed_at ?? '',
    set: (v: string) => {
        settingsForm.closed_at = v
    },
})
const visibleFor = computed({
    get: () => [...settingsForm.visible_for],
    set: (v: string[]) => {
        settingsForm.visible_for = v
    },
})

const fieldErrors = computed(() => ({
    title: settingsForm.errors.title,
    description: settingsForm.errors.description,
    closed_at: settingsForm.errors.closed_at,
    visible_for: settingsForm.errors.visible_for,
}))

function syncFieldsFromProps(): void {
    const raw: BackendField[] = JSON.parse(JSON.stringify(props.fields || []))
    raw.sort((a, b) => a.order - b.order)
    const mapped = raw.map((f) => fromBackendField(f))
    const { banner: syntheticBanner, canvasFields } = extractFormBannerFromBuilderFields(mapped)

    bannerState.id = syntheticBanner.id
    bannerState.bannerUrl = props.form.banner_url ?? syntheticBanner.bannerUrl
    bannerState.caption = props.form.banner_caption ?? syntheticBanner.caption
    bannerState.bannerFileName = syntheticBanner.bannerFileName

    formFields.value = canvasFields
}

watch(
    () => props.fields,
    () => syncFieldsFromProps(),
    { immediate: true, deep: true },
)
watch(
    () => props.form,
    (f) => {
        if (!f) return
        settingsForm.title = f.title
        settingsForm.description = f.description
        settingsForm.closed_at = f.closed_at ?? ''
        settingsForm.visible_for = [...f.visible_for]
        settingsForm.banner_url = f.banner_url ?? ''
        settingsForm.banner_caption = f.banner_caption ?? ''
        Object.assign(formMetadata, parseFormRegistrationMetadata(f.metadata))
    },
    { deep: true, immediate: true },
)

const submissionsHref = computed(
    () => `/admin/dashboard/events/${props.event.id}/forms/${props.form.id}/submissions`,
)

function onSave(): void {
    settingsForm.banner_url = bannerState.bannerUrl
    settingsForm.banner_caption = bannerState.caption

    const merged = prependFormBannerToBackendPayload(formFields.value, bannerState)
    const backendFields = toBackendFields(merged)

    settingsForm
        .transform((data) => ({
            ...data,
            fields: backendFields,
            metadata: toFormMetadataPayload(formMetadata),
        }))
        .put(props.updateFormUrl, {
            preserveScroll: true,
            onSuccess: () => toast.success('Form and fields saved successfully.'),
        })
}
</script>

<template>
    <Head :title="`Edit: ${form.title}`" />

    <FormBuilderWorkspace
        v-model:form-title="formTitle"
        v-model:form-description="formDescription"
        v-model:closed-at="closedAt"
        v-model:visible-for="visibleFor"
        v-model:banner="bannerState"
        v-model:form-fields="formFields"
        v-model:form-metadata="formMetadata"
        :event="event"
        :toolbar-subtitle="`Edit form · ${event.title}`"
        save-label="Save All"
        :processing="settingsForm.processing"
        :field-errors="fieldErrors"
        @save="onSave"
    >
        <template #toolbar-extra>
            <Button
                variant="outline"
                size="sm"
                class="rounded-full border-border/80 px-2.5 text-xs font-medium shadow-sm sm:px-3 sm:text-sm"
                as-child
            >
                <Link :href="submissionsHref" title="Lihat pengiriman form">
                    <span class="max-sm:hidden">Pengiriman</span>
                    <span class="sm:hidden">Kiriman</span>
                </Link>
            </Button>
        </template>
    </FormBuilderWorkspace>
</template>
