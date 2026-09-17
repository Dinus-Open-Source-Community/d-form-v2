<script setup lang="ts">
import { onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import ApplicantDetailContent, {
    type ApplicationDetail,
} from '@/components/modules/dashboard/recruitment/ApplicantDetailContent.vue'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    application: ApplicationDetail
    screeningReasonOptions: { value: string; label: string }[]
    divisionOptions: { id: string; name: string; code: string }[]
    membershipTypeOptions: { value: string; label: string }[]
}>()

onMounted(() => {
    setTopbar({
        title: props.application.full_name,
        subtitle: props.application.registration_number,
    })
})
</script>

<template>
    <Head :title="application.full_name" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <ApplicantDetailContent
            :application="application"
            :screening-reason-options="screeningReasonOptions"
            :division-options="divisionOptions"
            :membership-type-options="membershipTypeOptions"
        />
    </div>
</template>
