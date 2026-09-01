<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import { Card, CardContent } from '@/components/ui/card';
import RecruitApplicationFormFields from '@/components/modules/recruitment/RecruitApplicationFormFields.vue';
import { recruitmentStore } from '@/lib/recruitmentStore';
import type { IApplicant, IRecruitmentPeriod, RecruitDivisionId } from '@/types/recruitment';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    period: IRecruitmentPeriod;
    divisions: string[];
    submitUrl: string;
    alreadySubmitted: boolean;
}>();

const editId = computed(() => {
    const q = new URLSearchParams(window.location.search).get('edit');
    return q && q.length > 0 ? q : null;
});

const editingApplication = computed(() =>
    editId.value ? recruitmentStore.getApplicationById(editId.value) : undefined,
);

function handleSubmit(applicant: IApplicant): void {
    if (editingApplication.value) {
        recruitmentStore.resubmitApplication(editingApplication.value.id, applicant);
        router.visit(`/open-recruitment/tracking/${editingApplication.value.registrationNumber}`);
        return;
    }
    const application = recruitmentStore.addApplication(applicant, props.period.id);
    router.visit(`/open-recruitment/submitted/${application.registrationNumber}`);
}

function handleCancel(): void {
    if (editingApplication.value) {
        router.visit(`/open-recruitment/tracking/${editingApplication.value.registrationNumber}`);
        return;
    }
    router.visit('/open-recruitment');
}
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="Daftar Open Recruitment"
            description="Isi formulir pendaftaran Open Recruitment DOSCOM."
            :canonical-path="'/open-recruitment/apply'"
        />

        <div class="mx-auto max-w-3xl px-6 py-12">
            <div>
                <p class="text-primary text-xs font-semibold uppercase tracking-wider">
                    {{ editingApplication ? 'Perbaikan Data' : period.name }}
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">
                    {{ editingApplication ? 'Perbarui Data Pendaftaran' : 'Form Pendaftaran' }}
                </h1>
                <p class="text-muted-foreground mt-2">
                    Tutup pendaftaran:
                    {{ new Date(period.registrationEnd).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                </p>
            </div>

            <Card v-if="!alreadySubmitted || editingApplication" class="mt-8 rounded-2xl">
                <CardContent class="p-6 md:p-8">
                    <RecruitApplicationFormFields
                        :divisions="divisions as RecruitDivisionId[]"
                        :initial="editingApplication?.applicant ?? null"
                        @submit="handleSubmit"
                        @cancel="handleCancel"
                    />
                </CardContent>
            </Card>

            <Card v-else class="mt-8 rounded-2xl">
                <CardContent class="flex flex-col items-center gap-3 p-10 text-center">
                    <p class="font-semibold">Anda sudah mendaftar pada periode ini.</p>
                    <p class="text-muted-foreground text-sm">
                        Gunakan nomor pendaftaran untuk melacak status.
                    </p>
                </CardContent>
            </Card>
        </div>
    </LandingLayout>
</template>
