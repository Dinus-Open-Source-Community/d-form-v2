<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Camera, QrCode, ScanLine, ShieldAlert } from 'lucide-vue-next'

defineProps<{
    scannerContainerId: string
    eventUid: string
    cameras: Array<{ id: string; label: string }>
    selectedCameraId: string
    isStartingCamera: boolean
    isCameraReady: boolean
    permissionError: string
    successfulScansCount: number
    duplicateScansCount: number
    invalidScansCount: number
}>()

const manualQrInput = defineModel<string>('manualQrInput', { required: true })

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
                <Badge variant="outline" class="text-[11px]">Event UID: {{ eventUid }}</Badge>
            </div>
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
                <Select :model-value="selectedCameraId" @update:model-value="$emit('switchCamera', $event)">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Pilih kamera" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="camera in cameras" :key="camera.id" :value="camera.id">
                            {{ camera.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button class="md:min-w-36" :disabled="isStartingCamera || isCameraReady || !selectedCameraId" @click="$emit('startCamera')">
                    <Camera data-icon="inline-start" />
                    {{ isStartingCamera ? 'Menyalakan...' : 'Mulai kamera' }}
                </Button>

                <Button variant="outline" class="md:min-w-36" :disabled="!isCameraReady" @click="$emit('stopCamera')">
                    <ShieldAlert data-icon="inline-start" />
                    Stop kamera
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-4 p-4 md:p-5">
            <div class="relative overflow-hidden rounded-2xl border border-dashed border-border bg-muted/30 p-3">
                <div :id="scannerContainerId" class="mx-auto min-h-80 w-full max-w-[560px] rounded-xl bg-background" />
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
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Fallback manual (tanpa kamera)</p>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <Input
                        v-model="manualQrInput"
                        placeholder="Tempel isi QR di sini, contoh: DOSCOM-2026-REG-001"
                        @keydown.enter.prevent="$emit('submitManual')"
                    />
                    <Button variant="outline" class="sm:min-w-36" @click="$emit('submitManual')">
                        <QrCode data-icon="inline-start" />
                        Proses manual
                    </Button>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                    <p class="text-xs text-muted-foreground">Check-in berhasil</p>
                    <p class="text-lg font-semibold text-success">{{ successfulScansCount }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                    <p class="text-xs text-muted-foreground">Sudah scan</p>
                    <p class="text-lg font-semibold text-warning">{{ duplicateScansCount }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background px-3 py-2.5">
                    <p class="text-xs text-muted-foreground">Tidak valid</p>
                    <p class="text-lg font-semibold text-destructive">{{ invalidScansCount }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
