<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { recruitmentStore } from '@/lib/recruitmentStore';
import type { ICorrectionRequest } from '@/types/recruitment';
import { computed, onMounted, ref } from 'vue';
import { Check, X, UserCheck, UserX } from 'lucide-vue-next';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardLayout });

defineProps<{
    corrections: unknown[];
}>();

const requests = computed<ICorrectionRequest[]>(() =>
    recruitmentStore.state.applications.flatMap((a) => a.correctionRequests),
);

const pendingCount = computed(() => requests.value.filter((r) => r.status === 'pending').length);

const rejectTarget = ref<ICorrectionRequest | null>(null);
const rejectReason = ref('');

function openRejectModal(req: ICorrectionRequest): void {
    rejectTarget.value = req;
    rejectReason.value = '';
}

function closeRejectModal(): void {
    rejectTarget.value = null;
    rejectReason.value = '';
}

function confirmReject(): void {
    if (!rejectTarget.value || !rejectReason.value.trim()) return;
    recruitmentStore.rejectCorrection(rejectTarget.value.id, rejectReason.value.trim());
    closeRejectModal();
}

function approve(id: string): void {
    recruitmentStore.approveCorrection(id);
}

onMounted(() => {
    setTopbar({
        title: 'Correction Requests',
        subtitle: `Permintaan koreksi data applicant (${pendingCount.value} menunggu)`,
    });
});

const statusBadgeClass = (s: ICorrectionRequest['status']): string =>
    s === 'pending'
        ? 'bg-warning/15 text-warning border-warning/30'
        : s === 'approved'
          ? 'bg-success/15 text-success border-success/30'
          : 'bg-destructive/15 text-destructive border-destructive/30';

const statusLabel = (s: ICorrectionRequest['status']): string =>
    s === 'pending' ? 'Menunggu' : s === 'approved' ? 'Disetujui' : 'Ditolak';
</script>

<template>
    <Head title="Correction Requests — Recruitment" />

    <div class="flex flex-col gap-6">
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
                        <Badge
                            variant="outline"
                            :class="['rounded-md px-2.5 py-1 text-xs font-semibold', statusBadgeClass(req.status)]"
                        >
                            {{ statusLabel(req.status) }}
                        </Badge>
                    </div>

                    <!-- Info resolusi untuk yang sudah diputuskan -->
                    <div
                        v-if="req.status !== 'pending'"
                        class="mt-3 flex items-start gap-2 rounded-xl border border-border/60 bg-muted/20 p-3 text-xs"
                    >
                        <component
                            :is="req.status === 'approved' ? UserCheck : UserX"
                            class="mt-0.5 size-4 shrink-0 stroke-[1.75]"
                            :class="req.status === 'approved' ? 'text-success' : 'text-destructive'"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="text-muted-foreground">
                                oleh <span class="font-medium text-foreground">{{ req.resolvedBy ?? 'Admin' }}</span>
                                <span v-if="req.resolvedAt"> · {{ new Date(req.resolvedAt).toLocaleString('id-ID') }}</span>
                            </p>
                            <p v-if="req.status === 'rejected' && req.resolutionNote" class="leading-relaxed">
                                Alasan penolakan: <span class="font-medium text-foreground">{{ req.resolutionNote }}</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="req.status === 'pending'" class="mt-3 flex gap-2">
                        <Button size="sm" class="gap-1.5" @click="approve(req.id)">
                            <Check class="size-4 shrink-0 stroke-[1.75]" />
                            Approve
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            class="gap-1.5"
                            @click="openRejectModal(req)"
                        >
                            <X class="size-4 shrink-0 stroke-[1.75]" />
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

    <Dialog :open="rejectTarget !== null" @update:open="(v) => { if (!v) closeRejectModal() }">
        <DialogContent class="rounded-2xl sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-bold tracking-[-0.02em]">
                    Tolak koreksi {{ rejectTarget?.requestedBy ?? '' }}?
                </DialogTitle>
                <DialogDescription class="text-sm leading-relaxed text-muted-foreground">
                    Alasan penolakan wajib diisi. Applicant akan melihat alasan ini.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-2">
                <Label for="reject-reason">Alasan penolakan <span class="text-destructive">*</span></Label>
                <Textarea
                    id="reject-reason"
                    v-model="rejectReason"
                    rows="3"
                    placeholder="Contoh: permintaan duplikat, data sudah benar..."
                    class="text-sm"
                />
            </div>
            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="closeRejectModal">Batal</Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="!rejectReason.trim()"
                    @click="confirmReject"
                >
                    <X class="size-4 shrink-0 stroke-[1.75]" />
                    Tolak
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
