<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import MemberCheckInCard from '@/components/modules/dashboard/events/MemberCheckInCard.vue';
import MemberEventAboutCard from '@/components/modules/dashboard/events/MemberEventAboutCard.vue';
import MemberEventFactGrid from '@/components/modules/dashboard/events/MemberEventFactGrid.vue';
import MemberEventHero from '@/components/modules/dashboard/events/MemberEventHero.vue';
import MemberFormAccessList from '@/components/modules/dashboard/events/MemberFormAccessList.vue';
import MemberTeamInvitationCard from '@/components/modules/dashboard/events/MemberTeamInvitationCard.vue';
import { routes } from '@/lib/routes';
import type { IEventPortalFormSummary } from '@/types/form';
import type { TFormAnswerReviewStatus } from '@/types/registrationStatus';

defineOptions({ layout: DashboardLayout });

const props = defineProps<{
    event: IEvent;
    isRegistered: boolean;
    /** Undangan tim/bundle: belum accept/reject, bukan peserta resmi sampai dikonfirmasi. */
    pendingTeamInvitationUrl?: string | null;
    registrationStatus: TFormAnswerReviewStatus | null;
    qr_base64: string | null;
    registration_code: string | null;
    registrationForms?: IEventPortalFormSummary[];
    participantForms?: IEventPortalFormSummary[];
    checked_in_at?: string | null;
    check_in_form_title?: string | null;
}>();

/** Formulir pendaftaran tampil lebih dulu, lalu form peserta, masing-masing urut judul. */
const forms = computed<IEventPortalFormSummary[]>(() => {
    const byTitle = (left: IEventPortalFormSummary, right: IEventPortalFormSummary): number =>
        left.title.localeCompare(right.title, 'id');

    return [
        ...(props.registrationForms ?? []).slice().sort(byTitle),
        ...(props.participantForms ?? []).slice().sort(byTitle),
    ];
});

const invitationUrl = computed<string | null>(() =>
    props.pendingTeamInvitationUrl === undefined ? null : props.pendingTeamInvitationUrl
);

/** Check-in hanya relevan untuk peserta yang sudah diterima. */
const showCheckInCard = computed<boolean>(() => props.isRegistered && props.registrationStatus === 'accepted');

const registrationDetailUrl = computed<string | null>(() =>
    props.isRegistered ? routes.member.event.registration(props.event.slug) : null
);

const hasAside = computed<boolean>(() => invitationUrl.value !== null || showCheckInCard.value);
</script>

<template>
    <Head :title="event.title">
        <meta
            name="description"
            :content="`Detail acara ${event.title} di DForm: jadwal, lokasi, formulir, dan status pendaftaran.`"
        />
    </Head>

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 pb-6">
        <MemberEventHero :event="event" />

        <MemberEventFactGrid :event="event" />

        <div :class="hasAside ? 'grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start' : 'grid gap-5'">
            <div class="flex min-w-0 flex-col gap-5">
                <MemberEventAboutCard :description="event.description" />

                <MemberFormAccessList
                    :forms="forms"
                    :registration-detail-url="registrationDetailUrl"
                    :registered-form-title="check_in_form_title ?? null"
                />
            </div>

            <aside v-if="hasAside" class="flex min-w-0 flex-col gap-5 xl:sticky xl:top-24">
                <MemberTeamInvitationCard v-if="invitationUrl" :invitation-url="invitationUrl" />

                <MemberCheckInCard
                    v-if="showCheckInCard"
                    :qr-base64="qr_base64"
                    :registration-code="registration_code"
                    :checked-in-at="checked_in_at ?? null"
                />
            </aside>
        </div>
    </div>
</template>
