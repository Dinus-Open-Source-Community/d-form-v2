import { readWindow } from '@/adapters/browser/environment';
import { isSafeNavigationUrl } from '@/lib/urlPolicy';

/** Pathname saat ini; string kosong saat SSR. */
export function currentPathname(): string {
    return readWindow()?.location.pathname ?? '';
}

/** URL lengkap saat ini; string kosong saat SSR. */
export function currentHref(): string {
    return readWindow()?.location.href ?? '';
}

/** Nilai query string saat ini; `null` bila tidak ada. */
export function currentSearchParam(name: string): string | null {
    const browserWindow = readWindow();
    if (browserWindow === null) return null;

    return new URLSearchParams(browserWindow.location.search).get(name);
}

/** Query string lengkap termasuk `?`; string kosong bila tidak ada. */
export function currentSearch(): string {
    return readWindow()?.location.search ?? '';
}

/** Hash saat ini (termasuk `#`); string kosong bila tidak ada. */
export function currentHash(): string {
    return readWindow()?.location.hash ?? '';
}

/** Ganti URL tanpa menambah history. */
export function replaceUrl(url: string | URL): void {
    readWindow()?.history.replaceState(null, '', typeof url === 'string' ? url : url.toString());
}

/** Tambah URL ke history. */
export function pushUrl(url: string | URL): void {
    readWindow()?.history.pushState(null, '', typeof url === 'string' ? url : url.toString());
}

/**
 * Tambah entri history dengan state eksplisit dan mempertahankan state yang
 * sudah ada (mis. `history.state.page` milik Inertia) agar back/forward tetap
 * valid saat tab auth berpindah.
 */
export function pushHistoryState(state: Record<string, unknown>, url: string | URL): void {
    const browserWindow = readWindow();
    if (browserWindow === null) return;

    const current: unknown = browserWindow.history.state;
    const base = typeof current === 'object' && current !== null ? current : {};

    browserWindow.history.pushState({ ...base, ...state }, '', typeof url === 'string' ? url : url.toString());
}

/**
 * Navigasi penuh ke URL yang lolos allowlist skema (`http`, `https`, path internal).
 * URL `javascript:`/`data:` ditolak agar tidak mengeksekusi script di origin aplikasi.
 */
export function assignUrl(url: string): void {
    const browserWindow = readWindow();
    if (browserWindow === null || !isSafeNavigationUrl(url)) return;

    browserWindow.location.assign(url);
}

/** Kembali ke halaman sebelumnya; `false` bila tidak ada history. */
export function goBack(): boolean {
    const browserWindow = readWindow();
    if (browserWindow === null || browserWindow.history.length <= 1) return false;

    browserWindow.history.back();

    return true;
}

/** Posisi scroll vertikal halaman; `0` saat SSR. */
export function readScrollY(): number {
    return readWindow()?.scrollY ?? 0;
}

/** Scroll ke atas halaman dengan animasi halus. */
export function scrollToTop(): void {
    readWindow()?.scrollTo({ top: 0, behavior: 'smooth' });
}

/** Baca origin halaman; string kosong saat SSR. */
export function currentOrigin(): string {
    return readWindow()?.location.origin ?? '';
}
