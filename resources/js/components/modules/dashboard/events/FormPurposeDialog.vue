<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Check, FileText, Inbox, MessageSquare, Plus, Presentation } from 'lucide-vue-next';

/**
 * Dialog dua langkah sebelum membuat form.
 *
 * Langkah 1 memilih tujuan. Langkah 2 memilih mode pendaftaran untuk Pendaftaran dan D-Kelas.
 * Nilai yang dikirim selalu dari daftar di komponen ini; server memvalidasi ulang.
 */
const props = defineProps<{ open: boolean }>();

const emit = defineEmits<{
    close: [];
    confirm: [purpose: string, mode: string];
}>();

interface IPurposeOption {
    value: 'registration' | 'answer_collection' | 'feedback' | 'd_kelas';
    label: string;
    description: string;
    icon: typeof FileText;
    disabled?: boolean;
    badge?: string;
}

interface IModeOption {
    value: 'single' | 'bundle' | 'team';
    label: string;
    description: string;
}

const PURPOSES: readonly IPurposeOption[] = [
    {
        value: 'registration',
        label: 'Pendaftaran',
        description: 'Peserta mendaftar event, dengan pilihan individu, paket, atau tim.',
        icon: FileText,
    },
    {
        value: 'answer_collection',
        label: 'Pengumpulan Jawaban',
        description: 'Mengumpulkan isian dari peserta tanpa alur pendaftaran event.',
        icon: Inbox,
    },
    {
        value: 'feedback',
        label: 'Feedback',
        description: 'Survei atau penilaian setelah acara selesai.',
        icon: MessageSquare,
    },
    {
        value: 'd_kelas',
        label: 'D-Kelas',
        description: 'Form khusus kelas dengan kode redeem DU per peserta.',
        icon: Presentation,
    },
];

const MODES: readonly IModeOption[] = [
    { value: 'single', label: 'Single', description: 'Satu peserta mengisi satu form.' },
    { value: 'bundle', label: 'Bundle', description: 'Satu pendaftar mewakili beberapa anggota.' },
    { value: 'team', label: 'Team', description: 'Pendaftaran tim dengan undangan anggota.' },
];

const step = ref<1 | 2>(1);
const purpose = ref<IPurposeOption['value']>('registration');
const mode = ref<IModeOption['value']>('single');

watch(
    (): boolean => props.open,
    (open: boolean): void => {
        if (!open) return;

        step.value = 1;
        purpose.value = 'registration';
        mode.value = 'single';
    }
);

function choosePurpose(option: IPurposeOption): void {
    if (option.disabled === true) return;

    purpose.value = option.value;
}

function submit(): void {
    emit('confirm', purpose.value, mode.value);
}

/** Pendaftaran dan D-Kelas butuh langkah mode; tujuan lain langsung dibuat dengan mode single. */
function primaryAction(): void {
    if (step.value === 1 && (purpose.value === 'registration' || purpose.value === 'd_kelas')) {
        step.value = 2;

        return;
    }

    submit();
}
</script>

<template>
    <Dialog :open="props.open" @update:open="(value: boolean) => value || emit('close')">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-bold tracking-[-0.02em]">
                    {{ step === 1 ? 'Form ini untuk apa?' : 'Bagaimana peserta mendaftar?' }}
                </DialogTitle>
                <DialogDescription class="text-muted-foreground text-sm">
                    {{
                        step === 1
                            ? 'Tujuan menentukan aturan yang dipakai form ini. Masih bisa diubah di pengaturan selama bukan D-Kelas.'
                            : 'Mode menentukan berapa banyak peserta yang bisa diisi dalam satu pengiriman.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="step === 1" class="grid gap-3">
                <button
                    v-for="option in PURPOSES"
                    :key="option.value"
                    type="button"
                    class="border-border bg-card focus-visible:ring-ring/30 flex items-start gap-3 rounded-xl border px-4 py-3 text-left transition-colors focus-visible:ring-[3px] focus-visible:outline-none"
                    :class="[
                        purpose === option.value && option.disabled !== true
                            ? 'border-primary/60 bg-primary/5'
                            : 'hover:border-primary/40',
                        option.disabled === true ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                    ]"
                    :aria-pressed="purpose === option.value"
                    :aria-disabled="option.disabled === true"
                    :disabled="option.disabled === true"
                    @click="choosePurpose(option)"
                >
                    <component
                        :is="option.icon"
                        class="text-muted-foreground mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="text-foreground text-sm font-semibold">{{ option.label }}</span>
                            <span
                                v-if="option.badge"
                                class="border-border text-muted-foreground rounded-md border px-1.5 py-0.5 text-[10px] font-medium"
                            >
                                {{ option.badge }}
                            </span>
                        </span>
                        <span class="text-muted-foreground mt-0.5 block text-xs leading-snug">
                            {{ option.description }}
                        </span>
                    </span>
                    <Check v-if="purpose === option.value" class="text-primary size-4 shrink-0" aria-hidden="true" />
                </button>
            </div>

            <div v-else class="grid gap-3">
                <button
                    v-for="option in MODES"
                    :key="option.value"
                    type="button"
                    class="border-border bg-card focus-visible:ring-ring/30 flex items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors focus-visible:ring-[3px] focus-visible:outline-none"
                    :class="mode === option.value ? 'border-primary/60 bg-primary/5' : 'hover:border-primary/40'"
                    :aria-pressed="mode === option.value"
                    @click="mode = option.value"
                >
                    <span class="min-w-0 flex-1">
                        <span class="text-foreground block text-sm font-semibold">{{ option.label }}</span>
                        <span class="text-muted-foreground block text-xs leading-snug">{{ option.description }}</span>
                    </span>
                    <Check v-if="mode === option.value" class="text-primary size-4 shrink-0" aria-hidden="true" />
                </button>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button v-if="step === 2" type="button" variant="ghost" @click="step = 1">Kembali</Button>
                <span v-else />
                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" @click="emit('close')">Batal</Button>
                    <Button type="button" @click="primaryAction">
                        <Plus class="mr-1.5 size-4" aria-hidden="true" />
                        {{
                            step === 1 && (purpose === 'registration' || purpose === 'd_kelas') ? 'Lanjut' : 'Buat form'
                        }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
