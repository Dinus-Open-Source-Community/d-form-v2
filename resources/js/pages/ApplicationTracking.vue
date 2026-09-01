<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import RecruitTrackingTimeline from '@/components/modules/recruitment/RecruitTrackingTimeline.vue';
import RecruitStatusBadge from '@/components/modules/recruitment/RecruitStatusBadge.vue';
import { recruitmentStore } from '@/lib/recruitmentStore';
import { divisionLabels } from '@/lib/dummyRecruitment';
import type { IApplication } from '@/types/recruitment';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { SearchX } from 'lucide-vue-next';

const props = defineProps<{ registrationNumber?: string }>();

const search = ref(props.registrationNumber ?? '');

const application = computed<IApplication | undefined>(() => {
    const q = search.value.trim();
    if (!q) {
        return undefined;
    }
    return recruitmentStore.getApplicationByRegistrationNumber(q);
});

function track(): void {
    const q = search.value.trim();
    if (q) {
        router.get(`/open-recruitment/tracking/${q}`);
    }
}
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="Lacak Pendaftaran"
            description="Lacak status pendaftaran Open Recruitment DOSCOM."
            :canonical-path="'/open-recruitment/tracking'"
        />

        <div class="mx-auto max-w-2xl px-6 py-12">
            <h1 class="text-3xl font-bold tracking-tight">Lacak Pendaftaran</h1>
            <p class="text-muted-foreground mt-2">
                Masukkan nomor pendaftaran untuk melihat status.
            </p>

            <form class="mt-6 flex gap-2" @submit.prevent="track">
                <Input
                    v-model="search"
                    placeholder="OPREC-2026-0001"
                    class="font-mono"
                    aria-label="Nomor pendaftaran"
                />
                <Button type="submit">Lacak</Button>
            </form>

            <div v-if="application" class="mt-8 space-y-6">
                <Card class="rounded-2xl">
                    <CardContent class="p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-lg font-semibold">{{ application.applicant.fullName }}</p>
                                <p class="text-muted-foreground font-mono text-xs">{{ application.registrationNumber }}</p>
                                <p class="text-muted-foreground text-sm">
                                    {{ divisionLabels[application.applicant.primaryDivision] ?? application.applicant.primaryDivision }}
                                    · Semester {{ application.applicant.semester }}
                                </p>
                            </div>
                            <RecruitStatusBadge :stage="application.stage" />
                        </div>
                    </CardContent>
                </Card>

                <RecruitTrackingTimeline :stage="application.stage" />

                <div
                    v-if="application.stage === 'revision_required'"
                    class="rounded-xl border border-warning/40 bg-warning/5 p-4"
                >
                    <p class="text-sm font-medium">Data Anda memerlukan perbaikan.</p>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Silakan perbarui data dan kirim ulang agar dapat diproses kembali.
                    </p>
                    <Button
                        class="mt-3"
                        variant="outline"
                        @click="router.visit(`/open-recruitment/apply?edit=${application.id}`)"
                    >
                        Perbaiki Data
                    </Button>
                </div>

                <div v-if="application.screening" class="rounded-xl border p-4 text-sm">
                    <p class="font-medium">Keputusan Screening</p>
                    <p class="text-muted-foreground mt-1">
                        {{ application.screening.decision === 'pass' ? 'Berkas Anda lolos seleksi administrasi.' : '' }}
                        {{ application.screening.decision === 'revision' ? 'Perlu perbaikan data.' : '' }}
                        {{ application.screening.decision === 'reject' ? 'Pendaftaran Anda tidak lolos seleksi administrasi.' : '' }}
                    </p>
                    <p v-if="application.screening.reason" class="text-muted-foreground mt-1 text-xs">
                        {{ application.screening.reason }}
                    </p>
                </div>
            </div>

            <div
                v-else-if="registrationNumber"
                class="mt-8 flex items-start gap-3 rounded-xl border border-destructive/50 bg-destructive/5 p-4"
            >
                <SearchX class="mt-0.5 h-5 w-5 shrink-0 text-destructive" />
                <div>
                    <p class="font-medium text-destructive">Nomor tidak ditemukan</p>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Periksa kembali nomor pendaftaran Anda.
                    </p>
                </div>
            </div>
        </div>
    </LandingLayout>
</template>
