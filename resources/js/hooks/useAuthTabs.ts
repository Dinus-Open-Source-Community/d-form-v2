import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';
import { getAuthTab, resolveAuthTabFromPath } from '@/lib/authTabs';
import type { TAuthTab } from '@/lib/authTabs';
import { onWindowEvent } from '@/adapters/browser/domEvents';
import { currentPathname, pushHistoryState } from '@/adapters/browser/navigation';

export interface IUseAuthTabsOptions {
    /** Tab yang ditampilkan saat halaman pertama kali dimuat (mengikuti URL deep link). */
    initialTab: TAuthTab;
}

export interface IUseAuthTabsState {
    activeTab: Ref<TAuthTab>;
    setActiveTab: (tab: TAuthTab) => void;
}

/**
 * Kelola tab masuk/daftar: state aktif, sinkron URL lewat History API, dan tombol back.
 * Dipakai halaman auth agar pindah tab tanpa Inertia visit dan tanpa kehilangan isian form.
 */
export function useAuthTabs(options: IUseAuthTabsOptions): IUseAuthTabsState {
    const activeTab = ref<TAuthTab>(options.initialTab);

    /** Samakan tab dengan pathname saat ini (untuk back/forward dan deep link). */
    function syncTabWithLocation(): void {
        const tab = resolveAuthTabFromPath(currentPathname());
        if (tab !== null) activeTab.value = tab;
    }

    /** Pindah tab di tempat lalu catat URL-nya sebagai entri history baru. */
    function setActiveTab(tab: TAuthTab): void {
        if (tab === activeTab.value) return;

        activeTab.value = tab;
        pushHistoryState({ authTab: tab }, getAuthTab(tab).path);
    }

    let releasePopState: () => void = () => undefined;

    onMounted(() => {
        syncTabWithLocation();
        releasePopState = onWindowEvent('popstate', syncTabWithLocation);
    });

    onBeforeUnmount(() => {
        releasePopState();
    });

    return { activeTab, setActiveTab };
}
