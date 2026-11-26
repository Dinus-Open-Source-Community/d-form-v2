<script setup lang="ts">
import { computed } from 'vue';
import { formatRupiahPrice } from '@/lib/formatRupiahPrice';
import type { IFormPriceQuote } from '@/types/form';

const props = withDefaults(
    defineProps<{
        label: string;
        base: number;
        quote: IFormPriceQuote | null;
        /** Matikan saat konteks luar sudah menampilkan label field (mis. kartu builder). */
        showLabel?: boolean;
    }>(),
    { showLabel: true }
);

const discount = computed<number>(() => props.quote?.discount ?? 0);
const finalPrice = computed<number>(() => props.quote?.final ?? props.base);
const hasDiscount = computed<boolean>(() => discount.value > 0 && finalPrice.value < props.base);
const baseLabel = computed<string>(() => (props.showLabel ? props.label : 'Harga'));
const discountLabel = computed<string>(() => {
    const type = props.quote?.discount_type;
    const value = props.quote?.discount_value;

    return type === 'percentage' && value !== null ? `Diskon ${value}%` : 'Diskon';
});
</script>

<template>
    <div class="border-border bg-card rounded-2xl border px-5 py-4 shadow-xs">
        <!-- Tanpa diskon: satu baris harga, label kiri dan nominal kanan. -->
        <div v-if="!hasDiscount" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <p v-if="props.showLabel" class="text-muted-foreground text-sm font-semibold">{{ label }}</p>
            <p class="font-display text-foreground text-2xl font-bold tracking-[-0.025em] tabular-nums">
                {{ formatRupiahPrice(finalPrice) }}
            </p>
        </div>

        <!-- Dengan diskon: rincian baris seperti ringkasan pesanan, total dipisah garis. -->
        <div v-else class="space-y-1 text-sm tabular-nums">
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-muted-foreground">{{ baseLabel }}</span>
                <span class="text-foreground">{{ formatRupiahPrice(base) }}</span>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-muted-foreground">{{ discountLabel }}</span>
                <span class="text-foreground">-{{ formatRupiahPrice(discount) }}</span>
            </div>
            <div class="border-border mt-2 flex items-baseline justify-between gap-4 border-t pt-2">
                <span class="text-foreground font-semibold">Total</span>
                <span class="font-display text-foreground text-xl font-bold tracking-[-0.02em]">
                    {{ formatRupiahPrice(finalPrice) }}
                </span>
            </div>
        </div>
    </div>
</template>
