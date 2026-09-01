<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import RecruitApplicantTable from '@/components/modules/recruitment/RecruitApplicantTable.vue';
import { recruitmentStore } from '@/lib/recruitmentStore';
import { computed, ref, onMounted } from 'vue';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardLayout });

defineProps<{
    applications: unknown[];
}>();

onMounted(() => {
    setTopbar({ title: 'Daftar Applicant', subtitle: 'Semua pendaftar Open Recruitment' });
});

const search = ref('');
const filter = ref('all');

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    return recruitmentStore.state.applications.filter((a) => {
        const matchSearch =
            !q ||
            a.applicant.fullName.toLowerCase().includes(q) ||
            a.applicant.nim.toLowerCase().includes(q) ||
            a.registrationNumber.toLowerCase().includes(q);
        const matchFilter =
            filter.value === 'all' ||
            (filter.value === 'screening' && (a.stage === 'screening' || a.stage === 'submitted')) ||
            (filter.value === 'passed' && a.stage === 'document_passed') ||
            (filter.value === 'revision' && a.stage === 'revision_required') ||
            (filter.value === 'rejected' && (a.stage === 'document_rejected' || a.stage === 'rejected'));
        return matchSearch && matchFilter;
    });
});

function openDetail(id: string): void {
    router.visit(`/admin/dashboard/recruitment/applicants/${id}`);
}
</script>

<template>
    <Head title="Applicants — Recruitment" />

    <div class="flex flex-col gap-6">
        <Card class="rounded-2xl">
            <CardContent class="p-5">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <Input
                        v-model="search"
                        placeholder="Cari nama, NIM, atau nomor pendaftaran..."
                        class="max-w-xs"
                    />
                    <Select v-model="filter">
                        <SelectTrigger class="w-44">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua</SelectItem>
                            <SelectItem value="screening">Menunggu Screening</SelectItem>
                            <SelectItem value="passed">Berkas Lolos</SelectItem>
                            <SelectItem value="revision">Perlu Revisi</SelectItem>
                            <SelectItem value="rejected">Ditolak</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <RecruitApplicantTable :applications="filtered" @open="openDetail" />

                <div v-if="filtered.length === 0" class="text-muted-foreground py-10 text-center text-sm">
                    Tidak ada applicant yang cocok.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
