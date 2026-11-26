<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ImageOff } from 'lucide-vue-next';
import EventBannerImage from '@/components/core/EventBannerImage.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/dateTime';
import { eventRegistrationStatusLabel } from '@/lib/eventDetailSummary';
import { eventRegistrationBadge } from '@/lib/eventStatusUi';

const props = defineProps<{
    event: IEvent;
}>();

const bannerUnavailable = ref(false);
const showBanner = computed<boolean>(() => Boolean(props.event.banner_url) && !bannerUnavailable.value);

watch(
    () => props.event.banner_url,
    () => {
        bannerUnavailable.value = false;
    }
);

function handleBannerError(): void {
    bannerUnavailable.value = true;
}

const statusBadge = computed(() => eventRegistrationBadge(props.event.registration_status));
const statusLabel = computed<string>(() => eventRegistrationStatusLabel(props.event.registration_status));

/** Jadwal dan lokasi sengaja tidak diulang di sini: kartu fakta di bawah sudah memuat keduanya. */
const statusDescription = computed<string>(() => {
    const opens = formatDateTime({ value: props.event.registration_start, variant: 'date-long' });
    const closes = formatDateTime({ value: props.event.registration_end, variant: 'date-long' });

    switch (props.event.registration_status) {
        case 'open':
            return '';
        case 'full':
            return 'Kuota pendaftaran sudah penuh.';
        case 'closed':
            return closes === ''
                ? 'Periode pendaftaran telah berakhir.'
                : `Periode pendaftaran telah berakhir pada ${closes}.`;
        default:
            return opens === '' ? 'Pendaftaran belum dibuka.' : `Pendaftaran dibuka pada ${opens}.`;
    }
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="border-border/70 bg-muted/30 relative h-44 w-full overflow-hidden rounded-2xl border sm:h-60">
            <EventBannerImage
                v-if="showBanner"
                :src="event.banner_url"
                :alt="event.title"
                loading="eager"
                @load-error="handleBannerError"
            />
            <div v-else class="text-muted-foreground flex size-full flex-col items-center justify-center gap-1.5">
                <ImageOff class="size-5 opacity-60" aria-hidden="true" />
                <span class="text-xs font-medium">Pratinjau gambar acara</span>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <h1
                    class="font-display text-foreground min-w-0 text-2xl leading-tight font-bold tracking-tight break-words sm:text-3xl"
                >
                    {{ event.title }}
                </h1>
                <Badge variant="outline" :class="['px-2 py-0.5 text-[11px] font-semibold', statusBadge.className]">
                    {{ statusLabel }}
                </Badge>
            </div>

            <p v-if="statusDescription !== ''" class="text-muted-foreground text-sm leading-relaxed">
                {{ statusDescription }}
            </p>
        </div>
    </div>
</template>
