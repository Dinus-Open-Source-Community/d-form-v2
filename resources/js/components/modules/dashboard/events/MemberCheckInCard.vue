<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, Clock3, QrCode } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/dateTime';

const props = defineProps<{
    qrBase64: string | null;
    registrationCode: string | null;
    checkedInAt: string | null;
}>();

const isCheckedIn = computed<boolean>(() => props.checkedInAt !== null && props.checkedInAt !== '');

const checkedInLabel = computed<string>(() => {
    if (!isCheckedIn.value || props.checkedInAt === null) return '';

    const formatted = formatDateTime({ value: props.checkedInAt, variant: 'date-time-long' });

    return formatted === '' ? 'Check-in berhasil' : `Check-in berhasil pada ${formatted}`;
});

const hasQr = computed<boolean>(() => !isCheckedIn.value && props.qrBase64 !== null && props.qrBase64 !== '');
</script>

<template>
    <section class="border-success/25 bg-success/[0.06] flex flex-col gap-2.5 rounded-2xl border px-5 py-4">
        <h2 class="font-display text-success text-base font-bold tracking-tight">Check-in</h2>

        <template v-if="isCheckedIn">
            <div class="flex items-start gap-2.5">
                <CheckCircle2 class="text-success mt-0.5 size-5 shrink-0" aria-hidden="true" />
                <div class="min-w-0">
                    <p class="text-foreground text-sm font-semibold">Check-in berhasil</p>
                    <p class="text-muted-foreground mt-0.5 text-xs leading-relaxed">{{ checkedInLabel }}</p>
                </div>
            </div>
            <p class="text-muted-foreground text-[11px] leading-relaxed">
                Kode QR tidak ditampilkan lagi di halaman ini karena sudah dipindai. Kode tetap tersedia di halaman detail
                pendaftaran.
            </p>
        </template>

        <template v-else-if="hasQr">
            <div class="flex items-start gap-2.5">
                <Clock3 class="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <div class="min-w-0">
                    <p class="text-foreground text-sm font-semibold">Belum dipindai</p>
                    <p class="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                        Tunjukkan QR ini kepada panitia saat check-in.
                    </p>
                </div>
            </div>
            <img
                :src="`data:image/png;base64,${qrBase64}`"
                alt="Kode QR check-in"
                width="200"
                height="200"
                class="border-border mx-auto h-auto w-full max-w-[200px] rounded-lg border bg-white p-2"
            />
            <div v-if="registrationCode" class="text-center">
                <p class="text-muted-foreground text-xs">Kode manual</p>
                <p class="text-foreground mt-0.5 font-mono text-base font-bold tracking-[0.12em] select-all">
                    {{ registrationCode }}
                </p>
            </div>
            <p class="text-muted-foreground text-center text-[11px] leading-relaxed">
                Jika pemindaian gagal, berikan kode manual di atas.
            </p>
        </template>

        <div v-else class="flex items-start gap-2.5">
            <QrCode class="text-muted-foreground mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p class="text-muted-foreground text-xs leading-relaxed">
                QR check-in belum tersedia. Buka halaman detail pendaftaran atau gunakan email penerimaan.
            </p>
        </div>
    </section>
</template>
