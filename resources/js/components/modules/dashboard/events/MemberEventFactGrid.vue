<script setup lang="ts">
import { computed } from 'vue';
import { Banknote, CalendarRange, Clock3, MapPin } from 'lucide-vue-next';
import { sessionLabelMap } from '@/constants/eventDisplay';
import { compactDateRangeLabel, eventDurationLabel } from '@/lib/eventDetailSummary';
import { toCategoryList } from '@/lib/eventCategories';

const props = defineProps<{
    event: IEvent;
}>();

const scheduleLabel = computed<string>(() => {
    const label = compactDateRangeLabel(props.event.start_date ?? null, props.event.end_date ?? null);

    return label === '' ? 'Belum ditentukan' : label;
});

const durationLabel = computed<string>(() =>
    eventDurationLabel(props.event.start_date ?? null, props.event.end_date ?? null)
);

const sessionTokens = computed<string>(() =>
    toCategoryList(props.event.session)
        .map((session) => sessionLabelMap[session] ?? session)
        .join(', ')
);

const priceLabel = computed<string>(() =>
    props.event.price > 0 ? `Rp ${Number(props.event.price).toLocaleString('id-ID')}` : 'Gratis'
);

const locationLabel = computed<string>(() => props.event.location || 'Belum ditentukan');

interface IFactCell {
    readonly key: string;
    readonly label: string;
    readonly value: string;
    readonly hint: string;
    readonly icon: typeof CalendarRange;
}

/** Sesi menampilkan durasi sebagai nilai dan token sesi sebagai keterangan pada baris yang sama. */
const sessionValue = computed<string>(() => {
    if (durationLabel.value === '') return sessionTokens.value;

    return sessionTokens.value === '' ? durationLabel.value : `${durationLabel.value} · ${sessionTokens.value}`;
});

const cells = computed<IFactCell[]>(() => [
    { key: 'schedule', label: 'Jadwal', value: scheduleLabel.value, hint: '', icon: CalendarRange },
    { key: 'location', label: 'Lokasi', value: locationLabel.value, hint: '', icon: MapPin },
    { key: 'session', label: 'Sesi', value: sessionValue.value, hint: '', icon: Clock3 },
    { key: 'price', label: 'Biaya', value: priceLabel.value, hint: '', icon: Banknote },
]);
</script>

<template>
    <dl class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div
            v-for="cell in cells"
            :key="cell.key"
            class="border-border/70 bg-card flex min-h-[76px] min-w-0 flex-col justify-center gap-1 rounded-xl border p-4"
        >
            <dt
                class="text-muted-foreground flex items-center gap-1.5 text-[10px] font-semibold tracking-[0.1em] uppercase"
            >
                <component :is="cell.icon" class="text-primary size-3.5 shrink-0" aria-hidden="true" />
                {{ cell.label }}
            </dt>
            <dd class="text-foreground text-sm leading-snug font-semibold break-words">{{ cell.value }}</dd>
            <dd v-if="cell.hint !== ''" class="text-muted-foreground text-[11px]">{{ cell.hint }}</dd>
        </div>
    </dl>
</template>
