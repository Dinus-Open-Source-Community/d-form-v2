<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { Camera, QrCode, ScanLine, ShieldAlert } from 'lucide-vue-next'

const props = defineProps<{
    scannerContainerId: string
    eventLabel: string
    cameras: Array<{ id: string; label: string }>
    selectedCameraId: string
    isStartingCamera: boolean
    isCameraReady: boolean
    permissionError: string
    scanBusy: boolean
    targetOptions: SimpleSelectOption[]
}>()

const cameraOptions = computed<SimpleSelectOption[]>(() =>
    props.cameras.map((camera) => ({ value: camera.id, label: camera.label })),
)

const targetFilter = defineModel<string>('targetFilter', { required: true })
const registrationCodeInput = defineModel<string>('registrationCodeInput', { required: true })

defineEmits<{
    switchCamera: [id: string | undefined]
    startCamera: []
    stopCamera: []
    submitManual: []
}>()
</script>

<template>
    <Card class="overflow-hidden rounded-2xl border border-border/70">
        <CardHeader class="gap-3 border-b bg-muted/20">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <CardTitle class="text-base font-semibold">Area Scanner</CardTitle>
                <Badge variant="outline" class="max-w-[min(100%,280px)] truncate text-[11px]" :title="eventLabel">
                    {{ eventLabel }}
                </Badge>
            </div>

            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
                <SimpleSelect
                    :model-value="selectedCameraId"
                    :options="cameraOptions"
                    id="scanner-camera-select"
                    placeholder="Pilih kamera"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Pilih kamera"
                    @update:model-value="$emit('switchCamera', $event)"
                />

                <Button class="md:min-w-36" :disabled="isStartingCamera || isCameraReady || !selectedCameraId" @click="$emit('startCamera')">
                    <Camera data-icon="inline-start" />
                    {{ isStartingCamera ? 'Menyalakan...' : 'Mulai kamera' }}
                </Button>

                <Button variant="outline" class="md:min-w-36" :disabled="!isCameraReady" @click="$emit('stopCamera')">
                    <ShieldAlert data-icon="inline-start" />
                    Stop kamera
                </Button>
            </div>

            <div class="space-y-1.5">
                <Label for="scan-target-filter" class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                    Filter tampilan
                </Label>
                <SimpleSelect
                    v-model="targetFilter"
                    :options="targetOptions"
                    id="scan-target-filter"
                    placeholder="Semua acara"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Filter tampilan per acara"
                />
                <p class="text-muted-foreground text-xs">
                    Soft filter: QR dari acara lain tetap tercatat ke acara aslinya, hanya tampilan panel yang difokuskan.
                </p>
            </div>
        </CardHeader>
        <CardContent class="space-y-4 p-4 md:p-5">
            <div class="relative overflow-hidden rounded-2xl border border-dashed border-border bg-muted/30 p-3">
                <div :id="scannerContainerId" class="min-h-80 w-full rounded-xl bg-background" />
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="rounded-xl border-2 border-primary/35 px-8 py-10">
                        <ScanLine class="size-9 text-primary/70" />
                    </div>
                </div>
            </div>

            <div
                v-if="permissionError"
                class="rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
            >
                {{ permissionError }}
            </div>

            <div class="rounded-xl border border-border/70 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Input manual (tanpa kamera)</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Isi kode registrasi peserta, mis.
                    <span class="font-mono text-[11px]">OPREC-2026-00001</span>
                    atau kode registrasi event, lalu tekan Proses check-in.
                </p>
                <div class="mt-3 grid gap-2">
                    <Input
                        v-model="registrationCodeInput"
                        placeholder="OPREC-2026-00001 atau kode registrasi event"
                        :disabled="scanBusy"
                        @keydown.enter.prevent="$emit('submitManual')"
                    />
                    <Button variant="outline" class="w-full sm:w-auto sm:min-w-36" :disabled="scanBusy" @click="$emit('submitManual')">
                        <QrCode data-icon="inline-start" />
                        {{ scanBusy ? 'Memproses…' : 'Proses check-in' }}
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
