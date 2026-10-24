import { nextTick, onMounted, ref, watch } from 'vue';
import type { Ref } from 'vue';

export interface IUseOtpCodeInputOptions {
    /** Nilai gabungan dari parent (v-model); sumber kebenaran tetap parent. */
    modelValue: Ref<string>;
    /** Banyak kotak kode yang dirender. */
    length: number;
    /** Dipanggil setiap digit berubah. */
    onChange: (value: string) => void;
    /** Dipanggil ketika semua kotak terisi. */
    onComplete: (value: string) => void;
}

export interface IUseOtpCodeInputState {
    /** Digit per kotak; index 0 = kotak pertama. */
    digits: Ref<string[]>;
    /** Callback `ref` untuk mendaftarkan elemen input per index. */
    setInputElement: (index: number) => (element: Element | null) => void;
    handleInput: (index: number, event: Event) => void;
    handleKeydown: (index: number, event: KeyboardEvent) => void;
    handlePaste: (index: number, event: ClipboardEvent) => void;
    handleFocus: (event: FocusEvent) => void;
}

/** Buang semua non-angka lalu potong sesuai panjang kode. */
function toDigits(value: string, length: number): string[] {
    const clean = value.replace(/\D/g, '').slice(0, length);

    return Array.from({ length }, (_, index) => clean[index] ?? '');
}

/**
 * State dan interaksi enam kotak kode OTP: auto-advance, backspace, panah, dan paste.
 * Dipakai `OtpCodeInput` supaya komponen hanya merender dan mengikat event.
 */
export function useOtpCodeInput(options: IUseOtpCodeInputOptions): IUseOtpCodeInputState {
    const digits: Ref<string[]> = ref<string[]>(toDigits(options.modelValue.value, options.length));
    const elements: (HTMLInputElement | null)[] = [];

    /** Pindahkan fokus ke kotak index lalu pilih isinya agar ketikan langsung menggantikan digit lama. */
    function focusAt(index: number): void {
        void nextTick(() => {
            const element = elements[index];

            if (element === null || element === undefined) return;

            element.focus();
            element.select();
        });
    }

    /** Simpan digit baru ke parent dan beri tahu saat kode sudah lengkap. */
    function commit(next: string[]): void {
        digits.value = next;

        const joined = next.join('');
        options.onChange(joined);

        if (joined.length === options.length) options.onComplete(joined);
    }

    /** Tulis deretan angka mulai dari kotak `start`, sisanya dibiarkan. */
    function fillFrom(start: number, text: string): void {
        const next = [...digits.value];
        let cursor = start;

        for (const char of text.replace(/\D/g, '')) {
            if (cursor >= options.length) break;

            next[cursor] = char;
            cursor += 1;
        }

        commit(next);
        focusAt(Math.min(cursor, options.length - 1));
    }

    function setInputElement(index: number): (element: Element | null) => void {
        return (element: Element | null): void => {
            elements[index] = element instanceof HTMLInputElement ? element : null;
        };
    }

    function handleInput(index: number, event: Event): void {
        if (!(event.target instanceof HTMLInputElement)) return;

        const element = event.target;
        const typed = element.value.replace(/\D/g, '');

        // Tempel/ketik cepat bisa menghasilkan lebih dari satu digit di satu kotak.
        if (typed.length > 1) {
            fillFrom(index, typed);
        } else {
            const next = [...digits.value];
            next[index] = typed;

            commit(next);

            if (typed !== '' && index < options.length - 1) focusAt(index + 1);
        }

        // Samakan isi kotak dengan state: karakter non-digit dibuang, tempelan panjang dipotong.
        const expected = digits.value[index] ?? '';

        if (element.value !== expected) element.value = expected;
    }

    function handleKeydown(index: number, event: KeyboardEvent): void {
        if (event.key === 'Backspace') {
            event.preventDefault();

            const next = [...digits.value];
            const target = next[index] === '' && index > 0 ? index - 1 : index;
            next[target] = '';

            commit(next);
            focusAt(target);

            return;
        }

        if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault();
            focusAt(index - 1);

            return;
        }

        if (event.key === 'ArrowRight' && index < options.length - 1) {
            event.preventDefault();
            focusAt(index + 1);
        }
    }

    function handlePaste(index: number, event: ClipboardEvent): void {
        const text = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '');

        if (text === '') return;

        event.preventDefault();

        // Kode utuh selalu mengisi dari kotak pertama; potongan pendek mulai dari kotak aktif.
        fillFrom(text.length >= options.length ? 0 : index, text);
    }

    function handleFocus(event: FocusEvent): void {
        if (event.target instanceof HTMLInputElement) event.target.select();
    }

    // Parent mengosongkan kode (mis. setelah verifikasi gagal) → kosongkan kotak dan fokus ke awal.
    watch(options.modelValue, (value) => {
        if (value === digits.value.join('')) return;

        digits.value = toDigits(value, options.length);

        if (value === '') focusAt(0);
    });

    onMounted(() => focusAt(0));

    return { digits, setInputElement, handleInput, handleKeydown, handlePaste, handleFocus };
}
