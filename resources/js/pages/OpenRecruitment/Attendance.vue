<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { QrCode } from 'lucide-vue-next'

defineOptions({ layout: FormFillLayout })

defineProps<{
    trackingLoginUrl: string
}>()
</script>

<template>
    <Head title="Absensi Interview OpRec" />

    <div class="mx-auto max-w-lg px-2">
        <div class="mb-6 space-y-2 text-center">
            <p class="text-primary text-xs font-semibold tracking-wide uppercase">OpenRecruitment DOSCOM</p>
            <h1 class="text-2xl font-bold tracking-tight">Absensi interview</h1>
            <p class="text-muted-foreground text-sm">
                Absensi dilakukan oleh panitia di lokasi interview.
            </p>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <QrCode class="size-5" />
                    Cara absensi
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <ol class="text-muted-foreground list-decimal space-y-2 pl-5">
                    <li>Login ke halaman tracking pendaftaran kamu.</li>
                    <li>Tunjukkan QR code absensi interview kepada panitia.</li>
                    <li>Panitia akan memindai QR code melalui scanner admin.</li>
                    <li>Nomor antrean akan muncul di tracking setelah check-in berhasil.</li>
                </ol>

                <p class="rounded-xl border border-amber-200/80 bg-amber-50 px-4 py-3 text-amber-900">
                    Applicant tidak dapat melakukan check-in sendiri. Semua absensi harus melalui panitia.
                </p>

                <Button as-child class="w-full">
                    <Link :href="trackingLoginUrl">Buka tracking &amp; QR code</Link>
                </Button>
            </CardContent>
        </Card>

        <p class="text-muted-foreground mt-6 text-center text-xs">
            <Link :href="routes.openRecruitment.landing" class="text-primary underline-offset-4 hover:underline">
                Kembali ke landing OpRec
            </Link>
        </p>
    </div>
</template>
