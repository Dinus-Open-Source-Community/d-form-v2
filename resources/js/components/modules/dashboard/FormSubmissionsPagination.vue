<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { submissionPaginationLabel } from '@/lib/formSubmissionsUi'

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

defineProps<{
    links: PaginationLink[] | undefined
    lastPage: number
}>()
</script>

<template>
    <div v-if="links && lastPage > 1" class="flex items-center justify-center gap-1.5 pt-8">
        <Button
            v-for="link in links"
            :key="link.label"
            variant="outline"
            size="sm"
            :class="[
                'h-9 min-w-9',
                link.active ? 'border-primary bg-primary/10 text-primary' : '',
                !link.url ? 'opacity-40' : '',
            ]"
            :disabled="!link.url"
            as-child
        >
            <Link v-if="link.url" :href="link.url">{{ submissionPaginationLabel(link.label) }}</Link>
            <span v-else>{{ submissionPaginationLabel(link.label) }}</span>
        </Button>
    </div>
</template>
