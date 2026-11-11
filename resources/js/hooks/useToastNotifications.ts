import { toast } from 'vue-sonner';
import { buildAutosaveFailureMessage } from '@/lib/autosaveFailureMessage';
import {
    humanizeErrorMessage,
    parseApiErrorMessage,
    parseValidationErrors,
    type TErrorMessageContext,
    type TValidationErrors,
} from '@/lib/error-message';

/** Opsi tampilan toast bebas; `duration` dalam milidetik, `id` menggantikan toast dengan id sama. */
export interface IToastNotificationOptions {
    title?: string;
    description?: string;
    duration?: number;
    id?: string | number;
}

/** Flash session Inertia yang dipetakan menjadi toast. */
export interface IToastFlash {
    type?: string;
    message?: string;
}

/**
 * Seam toast aplikasi: satu-satunya modul kode aplikasi yang memanggil
 * `vue-sonner`.
 *
 * `lib/error-message` hanya menyediakan fungsi murni (parsing/format pesan);
 * efek UI-nya berada di sini agar folder `lib/` tetap deterministik. Handler
 * memakai objek singleton sehingga aman dipanggil dari event handler, hook,
 * maupun modul yang dijalankan sebelum komponen ter-mount (mis. `app.js`).
 *
 * Pengecualian yang disengaja: fallback `useGlobalHttpStatusToast` tetap
 * memanggil `toast.error` langsung karena modul ini diimpor secara lazy di
 * jalur tersebut; bila chunk ini gagal dimuat, fallback harus tetap hidup.
 * `components/ui/sonner/Sonner.vue` adalah komponen library (tidak diedit).
 */
export interface IToastNotifications {
    /** Tampilkan toast error validasi; durasi menyesuaikan jumlah error. */
    showValidationErrorToast(errors: TValidationErrors, ctx?: TErrorMessageContext & { title?: string }): void;
    /** Handler `onError` Inertia; meneruskan bag error ke toast validasi. */
    handleInertiaFormErrors(errors: TValidationErrors, ctx?: TErrorMessageContext & { title?: string }): void;
    /** Toast error HTTP; pesan body diprioritaskan, lalu default per status. */
    showHttpErrorToast(status: number, body?: unknown, overrides?: Partial<Record<number, string>>): void;
    /** Toast error dari pesan bebas; pesan di-humanize sebelum ditampilkan. */
    showErrorToast(message: string, options?: IToastNotificationOptions): void;
    /** Toast sukses dengan pesan apa adanya. */
    showSuccessToast(message: string, options?: IToastNotificationOptions): void;
    /** Toast informasi dengan pesan apa adanya. */
    showInfoToast(message: string, options?: IToastNotificationOptions): void;
    /** Toast peringatan dengan pesan apa adanya. */
    showWarningToast(message: string, options?: IToastNotificationOptions): void;
    /** Toast netral (tipe default sonner) untuk pesan status singkat. */
    showToast(message: string, options?: IToastNotificationOptions): void;
    /** Toast dari flash session; `type === 'success'` menjadi toast sukses. */
    showFlashToast(flash: IToastFlash | null | undefined): void;
    /** Toast kegagalan autosave dengan aksi retry. */
    notifyAutosaveFailure(message: string, retry: () => void): void;
}

const notifications: IToastNotifications = {
    showValidationErrorToast(errors, ctx) {
        const parsed = parseValidationErrors(errors, ctx);
        const title = ctx?.title ?? 'Validasi gagal';

        if (parsed.length === 0) {
            toast.error(title, {
                description: 'Periksa kembali formulir dan lengkapi field yang ditandai.',
            });
            return;
        }

        if (parsed.length === 1) {
            const only = parsed[0];
            toast.error(title, {
                description: `${only.label}: ${only.message}`,
                duration: 6000,
            });
            return;
        }

        toast.error(title, {
            description: parsed.map((entry) => entry.line).join('\n'),
            duration: Math.min(14_000, 4000 + parsed.length * 800),
        });
    },

    handleInertiaFormErrors(errors, ctx) {
        notifications.showValidationErrorToast(errors, ctx);
    },

    showHttpErrorToast(status, body, overrides) {
        const defaults: Record<number, string> = {
            401: 'Anda perlu masuk terlebih dahulu.',
            403: 'Anda tidak memiliki izin untuk tindakan ini.',
            404: 'Data tidak ditemukan.',
            419: 'Sesi tidak valid atau sudah habis. Muat ulang halaman lalu coba lagi.',
            422: 'Data yang dikirim tidak valid. Periksa kembali formulir.',
            429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
            500: 'Terjadi kesalahan server. Coba lagi nanti.',
            503: 'Layanan sedang sibuk. Coba lagi nanti.',
            ...overrides,
        };

        const fromBody = body ? parseApiErrorMessage(body, '', status) : '';
        if (fromBody) {
            toast.error(fromBody);
            return;
        }

        toast.error(defaults[status] ?? `Permintaan gagal (kode ${status}).`);
    },

    showErrorToast(message, options) {
        const text = humanizeErrorMessage(message);

        if (options?.title) {
            toast.error(options.title, {
                description: text || options.description,
                duration: options.duration,
                id: options.id,
            });
            return;
        }

        toast.error(text, {
            description: options?.description,
            duration: options?.duration,
            id: options?.id,
        });
    },

    showSuccessToast(message, options) {
        toast.success(message, toSonnerOptions(options));
    },

    showInfoToast(message, options) {
        toast.info(message, toSonnerOptions(options));
    },

    showWarningToast(message, options) {
        toast.warning(message, toSonnerOptions(options));
    },

    showToast(message, options) {
        toast(message, toSonnerOptions(options));
    },

    showFlashToast(flash) {
        if (!flash?.message) return;

        const message = humanizeErrorMessage(flash.message);
        if (flash.type === 'success') {
            toast.success(message);
            return;
        }

        toast.error(message);
    },

    notifyAutosaveFailure(message, retry) {
        toast.error(buildAutosaveFailureMessage(message), {
            action: {
                label: 'Coba lagi',
                onClick: retry,
            },
        });
    },
};

/** Terjemahkan opsi seam ke payload sonner; properti kosong dibiarkan `undefined`. */
function toSonnerOptions(options?: IToastNotificationOptions):
    | {
          description?: string;
          duration?: number;
          id?: string | number;
      }
    | undefined {
    if (options === undefined) return undefined;

    return { description: options.description, duration: options.duration, id: options.id };
}

/** Akses seam toast aplikasi; objeknya stabil sehingga aman diambil sekali per pemakai. */
export function useToastNotifications(): IToastNotifications {
    return notifications;
}
