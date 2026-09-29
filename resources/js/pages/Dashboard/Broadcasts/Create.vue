<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface EventOption {
    id: string
    title: string
}

defineProps<{ events: EventOption[] }>()

onMounted(() => {
    setTopbar({ title: 'Buat Broadcast', subtitle: 'Langkah 1 — Konfigurasi' })
})

const form = useForm({
    name: '',
    schedule_date: '',
    schedule_time: '',
    delay_min: 5,
    delay_max: 15,
    event_id: '',
})

const steps = ['01 Konfigurasi', '02 Dataset', '03 Recipients', '04 Email', '05 Preview', '06 Schedule']

function submit(): void {
    form.post(routes.admin.broadcasts.store)
}
</script>

<template>
    <Head title="Buat Broadcast" />
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 pb-8">
        <ol class="text-muted-foreground flex flex-wrap gap-2 text-xs font-medium">
            <li v-for="(s, i) in steps" :key="s" :class="i === 0 ? 'text-foreground font-semibold' : ''">
                {{ s }}<span v-if="i < steps.length - 1" class="mx-2">→</span>
            </li>
        </ol>

        <Card class="rounded-2xl">
            <CardHeader><CardTitle>Konfigurasi Broadcast</CardTitle></CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div class="grid gap-2">
                    <Label for="name">Nama broadcast</Label>
                    <Input id="name" v-model="form.name" placeholder="Pengumuman Hasil Open Recruitment" />
                    <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="schedule_date">Tanggal jadwal</Label>
                        <Input id="schedule_date" v-model="form.schedule_date" type="date" />
                        <p v-if="form.errors.schedule_date" class="text-destructive text-sm">{{ form.errors.schedule_date }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="schedule_time">Jam jadwal</Label>
                        <Input id="schedule_time" v-model="form.schedule_time" type="time" />
                        <p v-if="form.errors.schedule_time" class="text-destructive text-sm">{{ form.errors.schedule_time }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="delay_min">Delay min (detik)</Label>
                        <Input id="delay_min" v-model.number="form.delay_min" type="number" min="0" max="3600" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="delay_max">Delay max (detik)</Label>
                        <Input id="delay_max" v-model.number="form.delay_max" type="number" min="0" max="3600" />
                    </div>
                </div>
                <p v-if="form.errors.delay_max" class="text-destructive text-sm">{{ form.errors.delay_max }}</p>

                <div class="grid gap-2">
                    <Label for="event_id">Event context (untuk <code v-pre>{{event_name}}</code>) — opsional</Label>
                    <select
                        id="event_id"
                        v-model="form.event_id"
                        class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                    >
                        <option value="">— Tanpa event —</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
                    </select>
                </div>

                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Menyimpan…' : 'Buat & lanjut ke dataset' }}
                </Button>
                <p class="text-muted-foreground text-xs">
                    Setelah dibuat, lengkapi dataset → snapshot → email → preview → schedule di halaman detail.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
