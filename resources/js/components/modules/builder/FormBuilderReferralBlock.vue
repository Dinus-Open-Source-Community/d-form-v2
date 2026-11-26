<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronDown, ChevronUp, GripVertical, Ticket } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    /** Posisi sisip blok di bagian aktif; `0` dan `fieldCount` menonaktifkan tombol naik/turun. */
    insertIndex: number;
    fieldCount: number;
    dragging: boolean;
}>();

const emit = defineEmits<{
    dragStart: [event: DragEvent];
    dragEnd: [];
    move: [direction: -1 | 1];
}>();

/** Kartu semu mengikuti pola kartu field: chip ikon + label tipe, judul, deskripsi. */
const canMoveUp = (): boolean => props.insertIndex > 0;
const canMoveDown = (): boolean => props.insertIndex < props.fieldCount;
</script>

<template>
    <div
        class="relative flex flex-col gap-3 transition-opacity duration-300"
        :class="props.dragging ? 'opacity-45' : 'opacity-100'"
    >
        <!-- Rail kiri sama seperti field: grip, lalu tombol naik/turun bulat. -->
        <div class="absolute top-1 -left-12 hidden flex-col items-center gap-1.5 pt-1 lg:flex">
            <div
                class="border-border bg-card text-muted-foreground hover:border-primary/45 hover:bg-primary/8 hover:text-primary ring-border/60 hover:ring-primary/25 grid size-10 cursor-grab place-items-center rounded-full border-2 border-transparent shadow-sm ring-1 transition-all duration-200 active:cursor-grabbing"
                draggable="true"
                title="Seret untuk memindahkan blok kode referral"
                @dragstart="emit('dragStart', $event)"
                @dragend="emit('dragEnd')"
            >
                <GripVertical class="size-4" />
            </div>
            <div class="flex flex-col gap-0.5">
                <Button
                    radius="icon"
                    type="button"
                    variant="outline"
                    size="icon"
                    class="border-border/80 hover:border-primary/40 hover:bg-primary/5 size-8 shadow-sm transition-all duration-200"
                    :disabled="!canMoveUp()"
                    title="Pindah ke atas"
                    aria-label="Naikkan blok kode referral"
                    @click="emit('move', -1)"
                >
                    <ChevronUp class="size-4" />
                </Button>
                <Button
                    radius="icon"
                    type="button"
                    variant="outline"
                    size="icon"
                    class="border-border/80 hover:border-primary/40 hover:bg-primary/5 size-8 shadow-sm transition-all duration-200"
                    :disabled="!canMoveDown()"
                    title="Pindah ke bawah"
                    aria-label="Turunkan blok kode referral"
                    @click="emit('move', 1)"
                >
                    <ChevronDown class="size-4" />
                </Button>
            </div>
        </div>

        <div
            data-testid="form-builder-referral-block"
            role="group"
            aria-label="Blok kode referral"
            class="border-border bg-card hover:border-primary/40 relative isolate rounded-xl border shadow-sm transition-[border-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
        >
            <div class="flex items-center gap-2.5 px-4 pt-2 sm:px-5">
                <span class="bg-primary/10 text-primary grid size-7 shrink-0 place-items-center rounded-md">
                    <Ticket class="size-4" :stroke-width="2.25" aria-hidden="true" />
                </span>
                <span
                    class="text-muted-foreground hidden min-w-0 truncate text-[11px] font-semibold [@media(min-width:360px)]:block"
                >
                    Blok referral
                </span>
            </div>

            <div class="space-y-1 px-4 pt-2.5 pb-4 sm:px-5 sm:pb-5">
                <p class="font-display text-foreground text-[15px] leading-snug font-semibold tracking-tight">
                    Kode referral
                </p>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    Blok otomatis dari fitur referral. Posisi di sini mengikuti letaknya pada form.
                </p>
            </div>
        </div>

        <!-- Bar aksi mobile, sama seperti field: rail kiri tersembunyi di layar kecil. -->
        <div
            class="border-border bg-muted/30 mt-2 flex items-center justify-between gap-1 rounded-xl border px-2 py-1.5 lg:hidden"
        >
            <div class="flex items-center gap-0.5">
                <Button
                    radius="icon"
                    variant="ghost"
                    size="icon-sm"
                    :disabled="!canMoveUp()"
                    aria-label="Naikkan blok kode referral"
                    @click="emit('move', -1)"
                >
                    <ArrowUp class="size-4" />
                </Button>
                <Button
                    radius="icon"
                    variant="ghost"
                    size="icon-sm"
                    :disabled="!canMoveDown()"
                    aria-label="Turunkan blok kode referral"
                    @click="emit('move', 1)"
                >
                    <ArrowDown class="size-4" />
                </Button>
            </div>
            <span class="text-muted-foreground px-2 text-[11px] font-medium">Blok otomatis</span>
        </div>
    </div>
</template>
