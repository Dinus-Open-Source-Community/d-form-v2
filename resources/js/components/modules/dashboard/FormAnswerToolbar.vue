<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import ChipToggle from '@/components/core/ChipToggle.vue';
import { Input } from '@/components/ui/input';
import {
    FORM_ANSWER_REFERRAL_FILTER_OPTIONS,
    FORM_ANSWER_REVIEW_STATUS_FILTER_OPTIONS,
} from '@/constants/formAnswerReview';
import type { TSubmissionReferralFilter } from '@/types/formSubmissions';
import type { TFormAnswerReviewStatus } from '@/types/registrationStatus';

defineProps<{
    search: string;
    reviewStatus: TFormAnswerReviewStatus | null;
    referral: TSubmissionReferralFilter | null;
    /** Chip referral disembunyikan saat fitur referral mati: filter tanpa hasil hanya membingungkan. */
    referralEnabled: boolean;
    /** True bila ada filter aktif; menampilkan tombol hapus filter. */
    hasActiveFilters: boolean;
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:reviewStatus': [value: TFormAnswerReviewStatus | null];
    'update:referral': [value: TSubmissionReferralFilter | null];
    reset: [];
}>();

/** Chip referral bersifat toggle: klik chip aktif mengembalikan filter ke "semua". */
function selectReferral(value: TSubmissionReferralFilter, current: TSubmissionReferralFilter | null): void {
    emit('update:referral', current === value ? null : value);
}
</script>

<template>
    <div class="border-border/70 flex flex-col gap-3 border-b px-4 py-2.5 sm:flex-row sm:items-center sm:gap-3 sm:px-5">
        <div class="relative w-full shrink-0 sm:w-64">
            <label for="form-answer-search" class="sr-only">Cari jawaban</label>
            <Search
                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                aria-hidden="true"
            />
            <Input
                id="form-answer-search"
                :model-value="search"
                type="search"
                placeholder="Cari nama, email, atau kode"
                autocomplete="off"
                class="pr-12 pl-9 sm:pr-9 [&::-webkit-search-cancel-button]:hidden"
                @update:model-value="emit('update:search', String($event))"
            />
            <button
                v-if="search !== ''"
                type="button"
                class="text-muted-foreground hover:text-foreground absolute top-1/2 right-0 flex size-[44px] -translate-y-1/2 items-center justify-center rounded-md transition-colors sm:size-7"
                aria-label="Bersihkan pencarian"
                @click="emit('update:search', '')"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>

        <!-- Satu baris chip yang menggeser horizontal di layar sempit, membungkus di layar lebar. -->
        <div
            class="-mx-1 flex min-w-0 flex-1 scrollbar-none items-center gap-2 overflow-x-auto px-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0"
        >
            <div class="flex shrink-0 items-center gap-2" role="group" aria-label="Filter status review">
                <ChipToggle
                    v-for="option in FORM_ANSWER_REVIEW_STATUS_FILTER_OPTIONS"
                    :key="option.label"
                    :label="option.label"
                    :active="reviewStatus === option.value"
                    @select="emit('update:reviewStatus', option.value)"
                />
            </div>

            <template v-if="referralEnabled">
                <span class="bg-border h-5 w-px shrink-0" aria-hidden="true" />
                <div class="flex shrink-0 items-center gap-2" role="group" aria-label="Filter referral">
                    <ChipToggle
                        v-for="option in FORM_ANSWER_REFERRAL_FILTER_OPTIONS"
                        :key="option.label"
                        :label="option.label"
                        :active="referral === option.value"
                        @select="selectReferral(option.value, referral)"
                    />
                </div>
            </template>

            <!-- Bentuk chip yang sama dengan filter, tone destruktif menandai aksi menghapus. -->
            <button
                v-if="hasActiveFilters"
                type="button"
                class="border-destructive/40 bg-destructive/5 text-destructive hover:border-destructive/60 hover:bg-destructive/10 inline-flex min-h-[44px] shrink-0 items-center rounded-lg border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:min-h-0"
                @click="emit('reset')"
            >
                Hapus filter
            </button>
        </div>

        <div class="flex shrink-0 items-center gap-2 self-end empty:hidden sm:self-auto">
            <slot name="actions" />
        </div>
    </div>
</template>
