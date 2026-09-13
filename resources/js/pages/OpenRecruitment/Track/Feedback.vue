<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import OpRecFeedbackForm from '@/components/modules/open-recruitment/OpRecFeedbackForm.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

defineOptions({ layout: FormFillLayout })

defineProps<{
    application: { full_name: string; registration_number: string }
    storeUrl: string
    dashboardUrl: string
}>()
</script>

<template>
    <Head title="Feedback OpRec" />

    <div class="mx-auto max-w-lg space-y-4 px-2 py-6">
        <div class="text-center">
            <h1 class="text-xl font-semibold">Feedback OpRec</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ application.full_name }} · {{ application.registration_number }}
            </p>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Bagaimana pengalamanmu?</CardTitle>
            </CardHeader>
            <CardContent>
                <OpRecFeedbackForm :store-url="storeUrl" />
                <Button as-child variant="link" class="mt-2 h-auto p-0 text-sm">
                    <Link :href="dashboardUrl">← Kembali ke portal</Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
