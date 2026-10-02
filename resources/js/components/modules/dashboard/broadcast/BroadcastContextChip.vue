<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Lock } from 'lucide-vue-next'
import type { TBroadcastContextKind } from '@/lib/broadcastHub'

const props = defineProps<{
    kind: Exclude<TBroadcastContextKind, 'none'>
    displayName: string
}>()

const kindLabel = props.kind === 'event' ? 'Event' : 'Periode'
</script>

<template>
    <div class="flex flex-wrap items-center gap-2" data-testid="broadcast-context-chip">
        <Badge variant="secondary" class="gap-1.5 py-1 text-xs font-semibold">
            <Lock class="size-3.5" aria-hidden="true" />
            {{ kindLabel }}: {{ props.displayName }}
        </Badge>
        <p class="text-xs text-muted-foreground">Konteks terkunci — tidak bisa diganti dari halaman ini.</p>
    </div>
</template>
