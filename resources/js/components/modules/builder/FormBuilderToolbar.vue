<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, Eye } from 'lucide-vue-next'

const props = withDefaults(
    defineProps<{
        backHref: string
        toolbarSubtitle: string
        headingTitle: string
        isReadyToSave: boolean
        validationIssueCount: number
        isEmpty: boolean
        processing: boolean
        saveLabel: string
        hideTitles?: boolean
        hideToolbar?: boolean
    }>(),
    { hideTitles: false, hideToolbar: false }
)

defineEmits<{
    preview: []
    save: []
}>()

const canTeleport = ref(false)

onMounted(() => {
    void nextTick(() => {
        const left = document.getElementById('dashboard-fb-nav-left')
        const right = document.getElementById('dashboard-fb-nav-right')
        if (left && right) canTeleport.value = true
    })
})
</script>

<template>
    <div
        v-if="!canTeleport && !props.hideTitles && !props.hideToolbar"
        class="mb-4 rounded-2xl border border-border/70 bg-card/95 p-3 shadow-sm sm:mb-5 sm:p-4"
    >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-2 sm:items-center sm:gap-3">
                <Button radius="icon" variant="ghost" size="icon-sm" class="mt-0.5 shrink-0 sm:mt-0" as-child>
                    <Link :href="backHref" aria-label="Kembali ke daftar form">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <div v-if="!props.hideTitles" class="min-w-0 flex-1">
                    <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase sm:text-xs">
                        <span class="line-clamp-2 break-words lg:truncate">
                            {{ toolbarSubtitle }}
                        </span>
                    </p>
                    <h1 class="text-foreground mt-0.5 line-clamp-2 break-words text-base font-semibold tracking-tight sm:text-lg lg:truncate">
                        {{ headingTitle || 'Form tanpa judul' }}
                    </h1>
                </div>
            </div>
            <div
                class="
 flex w-full flex-wrap items-center justify-start gap-2
 sm:w-auto sm:justify-end sm:gap-2.5
  "
            >
                <slot name="toolbar-extra" />
                <Button
                    variant="outline"
                    size="sm"
                    class="
 hidden border-border/80 bg-background/90 px-3 text-sm font-medium shadow-sm
 sm:inline-flex
  "
                    :disabled="isEmpty"
                    aria-label="Pratinjau formulir"
                    @click="$emit('preview')"
                >
                    <Eye class="size-4 shrink-0 sm:hidden" aria-hidden="true" />
                    <span>Pratinjau</span>
                </Button>
                <Button
                    size="sm"
                    class="
 hidden px-3 text-sm font-medium shadow-sm sm:inline-flex sm:px-4
  "
                    :disabled="processing"
                    @click="$emit('save')"
                >
                    <span class="sm:hidden">Simpan</span>
                    <span class="hidden sm:inline">{{ saveLabel }}</span>
                </Button>
            </div>
        </div>
    </div>

    <Teleport v-if="canTeleport && !props.hideTitles && !props.hideToolbar" to="#dashboard-fb-nav-left">
        <div class="flex min-w-0 items-start gap-2 sm:items-center sm:gap-3">
            <Button radius="icon" variant="ghost" size="icon-sm" class="mt-0.5 shrink-0 sm:mt-0" as-child>
                <Link :href="backHref" aria-label="Kembali ke daftar form">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <div v-if="!props.hideTitles" class="min-w-0 flex-1">
                <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase sm:text-xs">
                    <span class="max-sm:line-clamp-2 max-sm:break-words sm:truncate">
                        {{ toolbarSubtitle }}
                    </span>
                </p>
                <h1 class="text-foreground mt-0.5 max-sm:line-clamp-2 max-sm:break-words text-[15px] font-semibold tracking-tight sm:mt-0 sm:truncate sm:text-base">
                    {{ headingTitle || 'Form tanpa judul' }}
                </h1>
            </div>
        </div>
    </Teleport>

    <Teleport v-if="canTeleport && !props.hideTitles && !props.hideToolbar" to="#dashboard-fb-nav-right">
        <div
            class="
 flex w-full flex-wrap items-center justify-start gap-2
 sm:w-auto sm:justify-end sm:gap-2.5
 "
        >
            <slot name="toolbar-extra" />
            <Button
                variant="outline"
                size="sm"
                class="
 hidden border-border/80 bg-background/90 px-3 text-sm font-medium shadow-sm
 sm:inline-flex
 "
                :disabled="isEmpty"
                aria-label="Pratinjau formulir"
                @click="$emit('preview')"
            >
                <Eye class="size-4 shrink-0 sm:hidden" aria-hidden="true" />
                <span>Pratinjau</span>
            </Button>
            <Button
                size="sm"
                class="
 hidden px-3 text-sm font-medium shadow-sm sm:inline-flex sm:px-4
 "
                :disabled="processing"
                @click="$emit('save')"
            >
                <span class="sm:hidden">Simpan</span>
                <span class="hidden sm:inline">{{ saveLabel }}</span>
            </Button>
        </div>
    </Teleport>
</template>
