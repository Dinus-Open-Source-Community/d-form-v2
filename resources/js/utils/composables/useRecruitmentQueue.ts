import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'

export interface QueueEntryRow {
    id: string
    queue_number: number
    status: string
    status_label: string
    called_at: string | null
    completed_at: string | null
    application: {
        id: string
        full_name: string
        registration_number: string
    } | null
}

export interface QueueSnapshot {
    entries: QueueEntryRow[]
    current: QueueEntryRow | null
    next: QueueEntryRow | null
    stats: {
        waiting: number
        called: number
        completed: number
        total: number
    }
}

const POLL_INTERVAL_MS = 10_000

export function useRecruitmentQueue(pollUrl: string, initial: QueueSnapshot) {
    const queue = ref<QueueSnapshot>(initial)
    const polling = ref(true)
    let timer: ReturnType<typeof setInterval> | null = null

    async function refresh() {
        try {
            const { data } = await axios.get<QueueSnapshot>(pollUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            queue.value = data
        } catch {
            // Keep last snapshot on transient errors.
        }
    }

    function startPolling() {
        if (timer !== null) {
            return
        }

        timer = setInterval(() => {
            if (polling.value) {
                void refresh()
            }
        }, POLL_INTERVAL_MS)
    }

    function stopPolling() {
        if (timer !== null) {
            clearInterval(timer)
            timer = null
        }
    }

    onMounted(() => {
        startPolling()
    })

    onUnmounted(() => {
        stopPolling()
    })

    return {
        queue,
        polling,
        refresh,
        startPolling,
        stopPolling,
    }
}
