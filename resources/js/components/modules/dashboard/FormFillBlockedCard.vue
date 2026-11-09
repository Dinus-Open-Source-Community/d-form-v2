<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import { routes } from '@/lib/routes';

defineProps<{
    eventId: string;
    title: string;
    body: string;
    success?: boolean;
    primaryActionHref?: string | null;
    primaryActionLabel?: string;
}>();
</script>

<template>
    <Card>
        <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
            <CheckCircle2 v-if="success" class="text-success size-10" />
            <AlertCircle v-else class="text-warning size-10" />
            <p class="font-display text-foreground text-xl font-bold tracking-[-0.02em]">{{ title }}</p>
            <p class="text-muted-foreground max-w-md text-sm">{{ body }}</p>
            <Button v-if="primaryActionHref" variant="default" size="lg" class="mt-4" as-child>
                <Link :href="primaryActionHref">{{ primaryActionLabel ?? 'Continue' }}</Link>
            </Button>
            <Button variant="outline" size="lg" :class="primaryActionHref ? 'mt-2' : 'mt-6'" as-child>
                <Link :href="routes.member.event.show(eventId)">View event</Link>
            </Button>
        </CardContent>
    </Card>
</template>
