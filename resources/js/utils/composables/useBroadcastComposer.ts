import { computed, ref, type ComputedRef, type Ref } from 'vue'
import { isBroadcastComposerReady } from '@/lib/broadcastHub'

/** State awal composer (subject + body HTML). */
export interface IBroadcastComposerState {
    subject: string | null
    bodyHtml: string | null
}

/** Composer konten broadcast: subject + body + gerbang kelengkapan M-6. */
export function useBroadcastComposer(initial: IBroadcastComposerState): {
    subject: Ref<string>
    bodyHtml: Ref<string>
    composerReady: ComputedRef<boolean>
} {
    const subject = ref<string>(initial.subject ?? '')
    const bodyHtml = ref<string>(initial.bodyHtml ?? '')
    const composerReady = computed<boolean>(() =>
        isBroadcastComposerReady({ subject: subject.value, bodyHtml: bodyHtml.value }),
    )
    return { subject, bodyHtml, composerReady }
}
