<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue'
import { CheckCircle2, ArrowRight, ClipboardList } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    event: { id: string; slug: string; title: string }
    form: {
        id: string
        title: string
        purpose: 'registration' | 'other'
        success_content: string | null
    }
    isRegistrationForm: boolean
    eventUrl: string
    registrationUrl: string | null
}>()

const successContent = computed(() => {
    const html = props.form.success_content
    if (!html || !html.trim() || html.trim() === '<p></p>') return null
    return html
})
</script>

<template>
    <Head :title="`Berhasil — ${form.title}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 py-4 sm:gap-8 sm:py-8">
        <div class="flex flex-col items-center text-center">
            <div
                class="bg-success/10 text-success ring-success/20 mb-4 flex size-16 items-center justify-center rounded-full ring-1"
            >
                <CheckCircle2 class="size-8" aria-hidden="true" />
            </div>
            <h1 class="font-display text-foreground text-2xl font-bold tracking-tight sm:text-3xl">
                Formulir berhasil dikirim
            </h1>
            <p class="text-muted-foreground mt-2 max-w-md text-sm leading-relaxed sm:text-base">
                Terima kasih. Jawaban Anda untuk
                <span class="text-foreground font-medium">{{ form.title }}</span>
                pada acara
                <span class="text-foreground font-medium">{{ event.title }}</span>
                sudah kami terima.
            </p>
        </div>

        <Card v-if="successContent" class="border-border/70 rounded-2xl shadow-sm">
            <CardHeader class="border-border/50 bg-muted/10 border-b px-5 py-4 sm:px-6">
                <CardTitle class="font-display text-base font-bold tracking-tight sm:text-lg">
                    Informasi
                </CardTitle>
            </CardHeader>
            <CardContent class="px-5 py-5 sm:px-6 sm:py-6">
                <TiptapRichHtml :html="successContent" />
            </CardContent>
        </Card>

        <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
            <Button
                v-if="isRegistrationForm && registrationUrl"
                as-child
                class="h-11 rounded-xl font-semibold shadow-sm"
            >
                <Link :href="registrationUrl">
                    <ClipboardList class="mr-2 size-4" aria-hidden="true" />
                    Lihat detail pendaftaran
                </Link>
            </Button>
            <Button
                as-child
                :variant="isRegistrationForm && registrationUrl ? 'outline' : 'default'"
                class="h-11 rounded-xl font-semibold"
            >
                <Link :href="eventUrl">
                    Kembali ke acara
                    <ArrowRight class="ml-2 size-4" aria-hidden="true" />
                </Link>
            </Button>
        </div>
    </div>
</template>
