<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next'

defineProps<{
    eventId: string
    title: string
    body: string
    success?: boolean
}>()
</script>

<template>
    <Card class="mt-6">
        <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
            <CheckCircle2 v-if="success" class="size-10 text-success" />
            <AlertCircle v-else class="size-10 text-warning" />
            <p class="font-display text-xl font-bold tracking-[-0.02em] text-foreground">{{ title }}</p>
            <p class="max-w-md text-sm text-muted-foreground">{{ body }}</p>
            <Button variant="outline" size="lg" class="mt-6" as-child>
                <Link :href="`/dashboard/events/${eventId}`">View event</Link>
            </Button>
        </CardContent>
    </Card>
</template>
