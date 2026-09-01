<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { recruitmentStore } from '@/lib/recruitmentStore';
import type { ICorrectionRequest } from '@/types/recruitment';
import { computed } from 'vue';
import { Check, X } from 'lucide-vue-next';

defineOptions({ layout: DashboardLayout });

defineProps<{
    corrections: unknown[];
}>();

const requests = computed<ICorrectionRequest[]>(() =>
    recruitmentStore.state.applications.flatMap((a) => a.correctionRequests),
);

const pendingCount = computed(() => requests.value.filter((r) => r.status === 'pending').length);

function approve(id: string): void {
    recruitmentStore.approveCorrection(id);
}

function reject(id: string): void {
    recruitmentStore.rejectCorrection(id);
}
</script>

<template>
    <Head title="Correction Requests — Recruitment" />

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Correction Requests"
            :subtitle="`Permintaan koreksi data applicant (${pendingCount} menunggu).`"
            :back-href="'/admin/dashboard/recruitment'"
        />

        <div class="space-y-4">
            <Card v-for="req in requests" :key="req.id" class="rounded-2xl">
                <CardContent class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ req.requestedBy }}</p>
                            <p class="text-muted-foreground mt-0.5 text-sm">{{ req.reason }}</p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Field: {{ req.fields.join(', ') }}
                            </p>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {{ new Date(req.requestedAt).toLocaleString('id-ID') }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-warning/15 text-warning': req.status === 'pending',
                                'bg-success/15 text-success': req.status === 'approved',
                                'bg-destructive/10 text-destructive': req.status === 'rejected',
                            }"
                        >
                            {{ req.status === 'pending' ? 'Menunggu' : req.status === 'approved' ? 'Disetujui' : 'Ditolak' }}
                        </span>
                    </div>

                    <div v-if="req.status === 'pending'" class="mt-3 flex gap-2">
                        <Button size="sm" class="gap-1.5" @click="approve(req.id)">
                            <Check class="h-4 w-4" />
                            Approve
                        </Button>
                        <Button size="sm" variant="destructive" class="gap-1.5" @click="reject(req.id)">
                            <X class="h-4 w-4" />
                            Reject
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div v-if="requests.length === 0" class="text-muted-foreground py-12 text-center text-sm">
                Belum ada permintaan koreksi.
            </div>
        </div>
    </div>
</template>
