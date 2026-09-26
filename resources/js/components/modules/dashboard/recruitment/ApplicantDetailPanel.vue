<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import ApplicantDetailContent, { type ApplicationDetail } from './ApplicantDetailContent.vue'
import { applicantAllowsTrackingResend, userAllowsTrackingResend } from '@/lib/recruitmentApplicantCapabilities'
import useAuth from '@/utils/composables/useAuth'
import { CheckCircle2, Mail, Trophy, XCircle } from 'lucide-vue-next'

const props = withDefaults(
    defineProps<{
        application: ApplicationDetail | null
        loading: boolean
        reasonOptions?: { value: string; label: string }[]
        divisionOptions?: { id: string; name: string; code: string }[]
        membershipTypeOptions?: { value: string; label: string }[]
        editable?: boolean
    }>(),
    {
        reasonOptions: () => [],
        divisionOptions: () => [],
        membershipTypeOptions: () => [],
        editable: false,
    },
)

const emit = defineEmits<{ close: []; submitted: [] }>()

const page = usePage()
const user = useAuth(page.props)
const canScreen = computed(
    () => (props.application?.can_screen ?? false) && user.value?.can_screen_recruitment_applications === true,
)
const canVerify = computed(
    () => (props.application?.can_verify ?? false) && user.value?.can_screen_recruitment_applications === true,
)
const canDecideFinal = computed(
    () =>
        (props.application?.can_decide_final ?? false) &&
        user.value?.can_decide_recruitment_final === true,
)
const canResendTracking = computed(() => {
    const application = props.application
    if (!application) {
        return false
    }

    return applicantAllowsTrackingResend(application) && userAllowsTrackingResend(user.value)
})

const contentRef = ref<InstanceType<typeof ApplicantDetailContent> | null>(null)

const sheetOpen = computed<boolean>({
    get: () => props.application !== null,
    set: (value: boolean) => {
        if (!value) emit('close')
    },
})

function openScreening(action: 'revision' | 'reject') {
    contentRef.value?.openScreeningModal(action)
}

function openFinal(action: 'accept' | 'reject') {
    contentRef.value?.openFinalModal(action)
}

function verifyRegistration() {
    contentRef.value?.verifyApplication()
}

function passScreening() {
    contentRef.value?.passApplication()
}

function resendTracking() {
    contentRef.value?.requestResendTracking()
}

function handleSubmitted() {
    emit('submitted')
    emit('close')
}
</script>

<template>
    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="right"
            overlay-class="bg-black/60 backdrop-blur-sm"
            class="inset-y-0 right-0 h-full w-full gap-0 p-0 sm:inset-y-3 sm:right-3 sm:h-[calc(100%-1.5rem)] sm:w-[52rem] sm:max-w-[calc(100vw-1.5rem)] sm:rounded-2xl sm:border sm:shadow-xl"
        >
            <SheetHeader class="border-border/70 shrink-0 space-y-1 border-b py-4 pl-4 pr-12 text-left">
                <SheetTitle class="truncate text-base">
                    {{ application?.full_name ?? 'Detail peserta' }}
                </SheetTitle>
                <SheetDescription class="text-muted-foreground truncate text-xs">
                    {{ application?.registration_number }} · {{ application?.nim }}
                </SheetDescription>
                <div v-if="application" class="flex flex-wrap items-center justify-between gap-2 pt-1.5">
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                        <Badge variant="secondary">{{ application.stage_label }}</Badge>
                        <Badge variant="outline">{{ application.result_label }}</Badge>
                    </div>
                    <div v-if="editable" class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="canResendTracking"
                            size="sm"
                            variant="outline"
                            @click="resendTracking"
                        >
                            <Mail class="mr-2 size-4" />
                            Kirim ulang tracking
                        </Button>
                        <Button v-if="canVerify" size="sm" variant="secondary" @click="verifyRegistration">
                            Verifikasi
                        </Button>
                        <Button v-if="canDecideFinal" size="sm" variant="destructive" @click="openFinal('reject')">
                            <XCircle class="mr-2 size-4" />
                            Tolak final
                        </Button>
                        <Button v-if="canDecideFinal" size="sm" @click="openFinal('accept')">
                            <Trophy class="mr-2 size-4" />
                            Terima
                        </Button>
                        <Button v-if="canScreen" size="sm" variant="outline" @click="openScreening('revision')">
                            Revisi
                        </Button>
                        <Button v-if="canScreen" size="sm" variant="destructive" @click="openScreening('reject')">
                            <XCircle class="mr-2 size-4" />
                            Tolak
                        </Button>
                        <Button v-if="canScreen" size="sm" @click="passScreening">
                            <CheckCircle2 class="mr-2 size-4" />
                            Lolos
                        </Button>
                    </div>
                </div>
            </SheetHeader>

            <div
                v-if="application"
                :aria-busy="loading"
                class="min-h-0 flex-1 overflow-y-auto p-4 transition-opacity"
                :class="loading ? 'opacity-60' : ''"
            >
                <ApplicantDetailContent
                    ref="contentRef"
                    :key="application.id"
                    :application="application"
                    :readonly="!editable"
                    :screening-reason-options="reasonOptions"
                    :division-options="divisionOptions"
                    :membership-type-options="membershipTypeOptions"
                    :hide-revision-action="true"
                    :hide-actions="true"
                    @submitted="handleSubmitted"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
