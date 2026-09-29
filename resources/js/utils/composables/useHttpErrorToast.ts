import { router } from '@inertiajs/vue3'
import { showHttpErrorToast } from '@/lib/error-message'

let registered = false
let lastVisitMethod = 'get'

/**
 * Toast untuk kegagalan submit (POST/PUT/PATCH/DELETE) yang selama ini hanya
 * berakhir di halaman Error — mis. 403, 429, 500, 503 — tanpa pesan ringkas.
 *
 * Hanya menyentuh permintaan non-GET supaya navigasi GET biasa tidak berubah.
 * Registrasi sekali saja karena layout bisa dipasang ulang tiap navigasi.
 */
export function useHttpErrorToast(): void {
    if (registered) return
    registered = true

    router.on('before', (event) => {
        lastVisitMethod = event.detail.visit.method
    })

    router.on('success', (event) => {
        if (lastVisitMethod === 'get') return

        const page = event.detail.page
        if (page.component !== 'Error') return

        const status = page.props.status
        if (typeof status === 'number') {
            showHttpErrorToast(status)
        }
    })
}
