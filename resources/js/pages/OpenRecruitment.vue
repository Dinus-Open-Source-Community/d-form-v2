<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import RecruitLandingHero from '@/components/modules/recruitment/RecruitLandingHero.vue';
import RecruitTimelineSection from '@/components/modules/recruitment/RecruitTimelineSection.vue';
import RecruitDivisionCard from '@/components/modules/recruitment/RecruitDivisionCard.vue';
import type { IRecruitmentPeriod } from '@/types/recruitment';

defineProps<{
    period: IRecruitmentPeriod;
    divisions: Array<{ id: string; label: string; description: string }>;
    isOpen: boolean;
    registrationUrl: string;
}>();
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="Open Recruitment"
            description="Ikuti Open Recruitment DOSCOM dan kembangkan skill bersama komunitas."
            :canonical-path="'/open-recruitment'"
        />

        <RecruitLandingHero
            :period-name="period.name"
            :is-open="isOpen"
            :registration-url="registrationUrl"
        />

        <section class="mx-auto max-w-5xl px-6 py-12">
            <div class="text-center">
                <h2 class="text-2xl font-bold tracking-tight">Divisi Tersedia</h2>
                <p class="text-muted-foreground mt-2">Pilih divisi yang sesuai minat Anda.</p>
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <RecruitDivisionCard
                    v-for="d in divisions"
                    :key="d.id"
                    :id="d.id"
                    :label="d.label"
                    :description="d.description"
                />
            </div>
        </section>

        <RecruitTimelineSection :period="period" />

        <section class="mx-auto max-w-3xl px-6 pb-20 text-center">
            <h2 class="text-2xl font-bold tracking-tight">Syarat Pendaftaran</h2>
            <ul class="text-muted-foreground mx-auto mt-4 max-w-md list-disc space-y-1 pl-5 text-left">
                <li>Mahasiswa aktif yang memenuhi ketentuan DOSCOM.</li>
                <li>Semester berada pada rentang 1–3.</li>
                <li>Belum mendaftar pada periode Open Recruitment yang sama.</li>
            </ul>
        </section>
    </LandingLayout>
</template>
