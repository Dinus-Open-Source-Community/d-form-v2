<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import { CalendarDays, CheckCircle2, ClipboardList, ScanLine } from 'lucide-vue-next';

interface IUserDetailStats {
    events_joined: number;
    registrations_pending: number;
    registrations_accepted: number;
    recruitment_applications: number;
    scans_recorded: number;
}

interface IUserDetailStatCardsProps {
    stats: IUserDetailStats;
}

interface IStatCard {
    key: string;
    label: string;
    value: number;
    /** Satu baris konteks; hanya memakai angka yang memang dikirim server. */
    hint: string | null;
    icon: Component;
}

const props = defineProps<IUserDetailStatCardsProps>();

/** Empat angka ringkasan; tidak ada tren, persentase, atau delta karangan. */
const statCards = computed<IStatCard[]>(() => [
    {
        key: 'events-joined',
        label: 'Event diikuti',
        value: props.stats.events_joined,
        hint: null,
        icon: CalendarDays,
    },
    {
        key: 'registrations-accepted',
        label: 'Pendaftaran diterima',
        value: props.stats.registrations_accepted,
        hint: props.stats.registrations_pending > 0 ? `${props.stats.registrations_pending} menunggu` : null,
        icon: CheckCircle2,
    },
    {
        key: 'recruitment-applications',
        label: 'Lamaran OpRec',
        value: props.stats.recruitment_applications,
        hint: null,
        icon: ClipboardList,
    },
    {
        key: 'scans-recorded',
        label: 'Scan dicatat',
        value: props.stats.scans_recorded,
        hint: null,
        icon: ScanLine,
    },
]);
</script>

<template>
    <section
        class="bg-border/70 border-border overflow-hidden rounded-2xl border shadow-xs"
        aria-label="Ringkasan aktivitas akun"
    >
        <dl class="grid grid-cols-2 gap-px lg:grid-cols-4">
            <div
                v-for="stat in statCards"
                :key="stat.key"
                class="bg-card flex flex-col p-4 sm:p-5"
                data-testid="user-detail-stat"
                :data-stat="stat.key"
            >
                <dt class="text-muted-foreground flex min-h-[2.4rem] items-start gap-1.5 text-xs font-medium">
                    <component :is="stat.icon" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {{ stat.label }}
                </dt>
                <dd class="text-foreground mt-1 text-2xl font-semibold tabular-nums">{{ stat.value }}</dd>
                <dd v-if="stat.hint" class="text-muted-foreground mt-0.5 text-xs">{{ stat.hint }}</dd>
            </div>
        </dl>
    </section>
</template>
