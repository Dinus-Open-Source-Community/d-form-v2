<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { CheckCircle2, Copy, Mail } from 'lucide-vue-next'

defineOptions({ layout: FormFillLayout })

const props = defineProps<{
    registrationNumber: string
    periodName: string | null
    applicantName: string | null
}>()

const trackLoginUrl = computed(
    () => `${routes.openRecruitment.track.login}?reg=${encodeURIComponent(props.registrationNumber)}`,
)

async function copyRegistrationNumber() {
    try {
        await navigator.clipboard.writeText(props.registrationNumber)
    } catch {
        // clipboard may be unavailable
    }
}
</script>

<template>
    <Head title="Pendaftaran berhasil" />

    <div class="mx-auto max-w-md px-2 pb-8">
        <Card class="rounded-2xl border-border/70">
            <CardContent class="space-y-5 p-6">
                <div class="text-center">
                    <div class="bg-primary/10 text-primary mx-auto mb-3 flex size-12 items-center justify-center rounded-full">
                        <CheckCircle2 class="size-6" />
                    </div>
                    <h1 class="text-xl font-bold">Pendaftaran berhasil</h1>
                    <p v-if="applicantName" class="text-muted-foreground mt-1 text-sm">
                        Terima kasih, {{ applicantName }}.
                    </p>
                </div>

                <div class="rounded-xl border border-border/70 bg-muted/30 p-4 text-center">
                    <p class="text-muted-foreground text-xs tracking-wide uppercase">Nomor pendaftaran</p>
                    <p class="mt-1 font-mono text-lg font-semibold">{{ registrationNumber }}</p>
                    <Button variant="ghost" size="sm" class="mt-2 h-8 text-xs" @click="copyRegistrationNumber">
                        <Copy class="mr-1.5 size-3.5" />
                        Salin nomor
                    </Button>
                </div>

                <ol class="space-y-3 text-sm">
                    <li class="flex gap-3">
                        <span class="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">1</span>
                        <span class="text-muted-foreground">
                            <strong class="text-foreground">Cek email</strong> — token tracking & konfirmasi dikirim ke email pribadi.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">2</span>
                        <span class="text-muted-foreground">
                            <strong class="text-foreground">Simpan email</strong> — token tidak ditampilkan ulang di halaman ini.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">3</span>
                        <span class="text-muted-foreground">
                            <strong class="text-foreground">Pantau status</strong> — masuk portal kapan saja setelah email diterima.
                        </span>
                    </li>
                </ol>

                <div class="flex items-start gap-2 rounded-lg border border-dashed p-3 text-xs">
                    <Mail class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                    <p class="text-muted-foreground">
                        Tidak menerima email dalam 10 menit? Cek folder spam atau hubungi panitia OpRec.
                    </p>
                </div>

                <Button as-child class="w-full">
                    <Link :href="trackLoginUrl">Buka portal tracking</Link>
                </Button>

                <Button as-child variant="outline" class="w-full">
                    <Link :href="routes.openRecruitment.landing">Kembali ke landing</Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
