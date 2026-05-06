<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import FormFillHeaderBlock from '@/components/modules/dashboard/FormFillHeaderBlock.vue'
import FormFillBlockedCard from '@/components/modules/dashboard/FormFillBlockedCard.vue'
import FormFillFieldsList from '@/components/modules/dashboard/FormFillFieldsList.vue'
import { useFormFillPage } from '@/utils/composables/useFormFillPage'
import type {
    FormAccessStatus,
    FormFillPageEvent,
    FormFillPageForm,
} from '@/types/form'

defineOptions({ layout: FormFillLayout })

const props = defineProps<{
    event: FormFillPageEvent
    form: FormFillPageForm
    fields: IFormField[]
    submitUrl: string
    accessStatus: FormAccessStatus
    accessMessage: string
}>()

const ctx = reactive(useFormFillPage(props))
</script>

<template>
    <Head :title="`Register: ${props.form.title}`" />

    <div class="mx-auto max-w-2xl px-2">
        <FormFillHeaderBlock
            :form-title="props.form.title"
            :form-description="props.form.description ?? ''"
            :form-has-description="ctx.formHasDescription"
            :form-banner-image-src="ctx.formBannerImageSrc"
            :form-banner-caption="ctx.formBannerCaption"
        />

        <FormFillBlockedCard
            v-if="ctx.isBlocked"
            :event-id="props.event.id"
            :title="ctx.blockCopy.title"
            :body="ctx.blockCopy.body"
            :success="ctx.blockCopy.success"
        />

        <FormFillFieldsList
            v-else
            :fields="props.fields"
            :event-id="props.event.id"
            :ctx="ctx"
            @submit="ctx.submit"
        />
    </div>
</template>
