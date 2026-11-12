<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import type { IInterviewDetailPayload } from '@/types/recruitmentInterview';
defineProps<{ detail: IInterviewDetailPayload }>();
</script>
<template>
    <Card class="border-border/70 rounded-2xl">
        <CardContent class="p-6">
            <h2 class="text-sm font-semibold">Sesi interview</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-muted-foreground text-xs uppercase">Status</p>
                    <p class="mt-0.5 font-medium">{{ detail.interview?.status_label ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground text-xs uppercase">Lokasi</p>
                    <p v-if="detail.interview" class="mt-0.5 font-medium">
                        {{ detail.interview.location }} · {{ detail.interview.room }}
                    </p>
                    <p v-else class="text-muted-foreground mt-0.5">—</p>
                </div>
                <div v-if="detail.attendance?.checked_in_at" class="sm:col-span-2">
                    <p class="text-muted-foreground text-xs uppercase">Absen</p>
                    <p class="mt-0.5 font-medium">{{ detail.attendance.checked_in_at }}</p>
                </div>
            </div>

            <div
                v-if="detail.primary_evaluation?.notes"
                class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4"
            >
                <div class="flex items-center gap-2">
                    <span class="inline-flex size-2 rounded-full bg-rose-500" />
                    <h3 class="text-xs font-semibold tracking-wider text-rose-600 uppercase dark:text-rose-400">
                        Catatan Divisi Utama ({{ detail.application.primary_division ?? 'Divisi Pertama' }})
                    </h3>
                </div>
                <p class="text-foreground mt-2 text-sm break-words">
                    {{ detail.primary_evaluation.notes }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
