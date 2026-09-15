<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { Camera, ShieldAlert } from 'lucide-vue-next'

const props = defineProps<{
    scannerContainerId: string
    eventLabel: string
    cameras: Array<{ id: string; label: string }>
    selectedCameraId: string
    isStartingCamera: boolean
    isCameraReady: boolean
    isShutterActive: boolean
    permissionError: string
    targetOptions: SimpleSelectOption[]
}>()

const cameraOptions = computed<SimpleSelectOption[]>(() =>
    props.cameras.map((camera) => ({ value: camera.id, label: camera.label })),
)

const targetFilter = defineModel<string>('targetFilter', { required: true })

defineEmits<{
    switchCamera: [id: string | undefined]
    startCamera: []
    stopCamera: []
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
                    Filter riwayat
                </Label>
                <SimpleSelect
                    v-model="targetFilter"
                    :options="targetOptions"
                    id="scan-target-filter"
                    placeholder="Semua acara"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                    aria-label="Filter riwayat scan per acara"
                />
                <p class="text-muted-foreground text-xs">
                    Filter hanya menyaring riwayat scan. Scan tetap tercatat ke acara aslinya.
                </p>
            </div>
        </CardHeader>
        <CardContent class="space-y-4 p-4 md:p-5">
            <div class="relative overflow-hidden rounded-2xl border border-dashed border-border bg-muted/30 p-3">
                <div class="scanner-stage relative overflow-hidden rounded-xl">
                    <div
                        :id="scannerContainerId"
                        class="min-h-80 w-full overflow-hidden rounded-xl bg-background"
                    />

                    <div class="pointer-events-none absolute inset-0 z-20">
                        <div class="absolute inset-0 rounded-xl border-2 border-primary/25" />
                        <span class="absolute left-0 top-0 size-7 rounded-tl-xl border-l-4 border-t-4 border-primary/70" />
                        <span class="absolute right-0 top-0 size-7 rounded-tr-xl border-r-4 border-t-4 border-primary/70" />
                        <span class="absolute bottom-0 left-0 size-7 rounded-bl-xl border-b-4 border-l-4 border-primary/70" />
                        <span class="absolute bottom-0 right-0 size-7 rounded-br-xl border-b-4 border-r-4 border-primary/70" />

                        <div
                            v-if="isCameraReady && !isShutterActive"
                            class="scanner-sweep absolute inset-x-3 top-0 h-0.5 rounded-full bg-gradient-to-r from-transparent via-primary/70 to-transparent"
                        />

                        <div
                            v-if="!isCameraReady && !isShutterActive"
                            class="absolute inset-x-0 bottom-3 flex justify-center px-3"
                        >
                            <span class="bg-background/85 text-muted-foreground rounded-full px-3 py-1 text-xs font-medium shadow-sm">
                                Arahkan QR ke seluruh area pindai
                            </span>
                        </div>
                    </div>

                    <div v-if="isShutterActive" class="pointer-events-none absolute inset-0 z-30 rounded-xl">
                        <div class="shutter-flash absolute inset-0 rounded-xl bg-white" />
                        <div class="ring-success/80 absolute inset-0 rounded-xl ring-4 ring-inset" />
                    </div>
                </div>
            </div>

            <div
                v-if="permissionError"
                class="rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
            >
                {{ permissionError }}
            </div>
        </CardContent>
    </Card>
</template>

<style scoped>
@keyframes scanner-sweep {
    0% {
        top: 0;
        opacity: 0;
    }
    15% {
        opacity: 1;
    }
    85% {
        opacity: 1;
    }
    100% {
        top: calc(100% - 2px);
        opacity: 0;
    }
}

@keyframes shutter-flash {
    0% {
        opacity: 0.9;
    }
    100% {
        opacity: 0;
    }
}

.scanner-sweep {
    animation: scanner-sweep 3.2s ease-in-out infinite;
}

.shutter-flash {
    animation: shutter-flash 260ms ease-out forwards;
}

@media (prefers-reduced-motion: reduce) {
    .scanner-sweep,
    .shutter-flash {
        animation: none;
        display: none;
    }
}
</style>
