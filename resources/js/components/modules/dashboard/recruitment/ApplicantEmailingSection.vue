<script setup lang="ts">
import { computed } from 'vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
    RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT,
    RECRUITMENT_EMAIL_RESEND_OPTIONS,
    emailResendLabelOf,
    formatEmailResendAttempt,
    type IEmailResendPrereq,
    type IEmailResendTypeStatus,
    type TRecruitmentEmailResendType,
} from '@/lib/recruitmentEmailResend'
import { useApplicantEmailing } from '@/utils/composables/useApplicantEmailing'
import { ExternalLink, MessageCircle, Send } from 'lucide-vue-next'

const props = defineProps<{
    applicationId: string
    applicantName: string
    statusMap?: Partial<Record<TRecruitmentEmailResendType, IEmailResendTypeStatus>> | null
    prereq?: IEmailResendPrereq | null
    canResend: boolean
    whatsappGroupUrl?: string | null
}>()

const emit = defineEmits<{ resent: [] }>()

const emailing = useApplicantEmailing({
    applicationId: props.applicationId,
    applicantName: props.applicantName,
    statusMap: props.statusMap ?? null,
    prereq: props.prereq ?? null,
    canResend: props.canResend,
    onSent: () => emit('resent'),
})

const pendingLabel = computed<string>(() =>
    emailing.pendingType.value ? emailResendLabelOf(emailing.pendingType.value) : '',
)

function attemptLineOf(type: TRecruitmentEmailResendType): string {
    const status = emailing.statusOf(type)
    const count = status?.count_24h ?? 0
    return `Terakhir dicoba: ${formatEmailResendAttempt(status?.last_attempt_at ?? null)} · ${count}/${RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT} per 24 jam`
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-if="props.whatsappGroupUrl"
            class="flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            <MessageCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div class="min-w-0">
                <p class="font-medium">Grup WA periode</p>
                <a
                    :href="props.whatsappGroupUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex max-w-full items-center gap-1 break-all underline-offset-4 hover:underline"
                >
                    <span class="truncate">{{ props.whatsappGroupUrl }}</span>
                    <ExternalLink class="size-3.5 shrink-0" aria-hidden="true" />
                </a>
                <p class="mt-1 text-xs">Link ini tampil di email kelulusan (passed_screening).</p>
            </div>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardContent class="space-y-3 p-4 sm:p-5">
                <div
                    v-for="option in RECRUITMENT_EMAIL_RESEND_OPTIONS"
                    :key="option.type"
                    class="flex flex-col gap-2 rounded-xl border border-border/60 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ option.label }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ option.description }}</p>
                        <p class="mt-1 text-xs tabular-nums text-muted-foreground">
                            {{ attemptLineOf(option.type) }}
                        </p>
                        <p
                            v-if="emailing.reasonOf(option.type)"
                            role="note"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ emailing.reasonOf(option.type) }}
                        </p>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        class="shrink-0"
                        :disabled="emailing.reasonOf(option.type) !== null || emailing.isSending.value"
                        :title="emailing.reasonOf(option.type) ?? undefined"
                        @click="emailing.requestSend(option.type)"
                    >
                        <Send class="mr-2 size-4" aria-hidden="true" />
                        Kirim
                    </Button>
                </div>
            </CardContent>
        </Card>

        <ConfirmationModal
            :open="emailing.confirmOpen.value"
            :title="`Kirim ulang ${pendingLabel}?`"
            :description="`Email dikirim ke applicant (${props.applicantName}). Pengiriman tercatat dan masuk hitungan throttle harian.`"
            confirm-text="Kirim"
            cancel-text="Batal"
            :loading="emailing.isSending.value"
            @confirm="emailing.confirmSend"
            @cancel="emailing.cancelSend"
            @update:open="(v: boolean) => { emailing.confirmOpen.value = v }"
        />
    </div>
</template>
