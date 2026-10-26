import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { Ref } from 'vue'
import { shouldShowLivePreview } from '@/lib/formBuilderPreview'

export interface IFormBuilderLivePreview {
    rootRef: Ref<HTMLElement | null>
    livePreviewActive: Ref<boolean>
}

/**
 * Pantau lebar baris builder lewat `ResizeObserver` dan laporkan apakah kolom pratinjau live muat.
 * Dipakai `FormBuilderWorkspace` supaya tombol Pratinjau dan kolom live tidak tampil bersamaan.
 */
export function useFormBuilderLivePreview(): IFormBuilderLivePreview {
    const rootRef = ref<HTMLElement | null>(null)
    const livePreviewActive = ref(false)

    let observer: ResizeObserver | null = null

    /** Terapkan ambang lebar ke state reaktif. */
    function applyWidth(width: number): void {
        livePreviewActive.value = shouldShowLivePreview(width)
    }

    onMounted(() => {
        const element = rootRef.value
        if (!element || typeof ResizeObserver === 'undefined') return

        applyWidth(element.getBoundingClientRect().width)

        observer = new ResizeObserver((entries) => {
            const entry = entries[0]
            if (entry) applyWidth(entry.contentRect.width)
        })
        observer.observe(element)
    })

    onBeforeUnmount(() => {
        observer?.disconnect()
        observer = null
    })

    return { rootRef, livePreviewActive }
}
