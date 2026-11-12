import { routes } from '@/lib/routes';

export type TAuthTab = 'signin' | 'signup';

export interface IAuthTabConfig {
    value: TAuthTab;
    label: string;
    path: string;
    title: string;
    description: string;
}

/** Definisi tab masuk/daftar: label, URL, dan metadata SEO dipakai bersama halaman auth. */
const authTabConfigs: Record<TAuthTab, IAuthTabConfig> = {
    signin: {
        value: 'signin',
        label: 'Masuk',
        path: routes.auth.login,
        title: 'Masuk',
        description: 'Masuk ke akun DForm untuk mengelola acara, formulir, dan kehadiran.',
    },
    signup: {
        value: 'signup',
        label: 'Daftar',
        path: routes.auth.register,
        title: 'Daftar',
        description: 'Buat akun DForm untuk mengikuti acara dan mengelola pendaftaran.',
    },
};

/** Urutan tab di layar auth (Masuk lebih dulu). */
export const authTabs: readonly IAuthTabConfig[] = [authTabConfigs.signin, authTabConfigs.signup];

/** Ambil konfigurasi tab berdasarkan nilainya; dipakai untuk label, URL, dan SEO halaman. */
export function getAuthTab(tab: TAuthTab): IAuthTabConfig {
    return authTabConfigs[tab];
}

/** Cek apakah nilai dari komponen tab memang tab auth yang dikenal. */
export function isAuthTab(value: string): value is TAuthTab {
    return authTabs.some((tab) => tab.value === value);
}

/** Petakan pathname browser ke tab auth; `null` bila path bukan tab masuk/daftar. */
export function resolveAuthTabFromPath(pathname: string): TAuthTab | null {
    return authTabs.find((tab) => tab.path === pathname)?.value ?? null;
}
