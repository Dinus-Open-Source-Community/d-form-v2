<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import {
    Pencil,
    Trash2,
    RotateCcw,
    FileText,
    Users,
    BarChart3,
    Plus,
    ChevronDown,
    ChevronUp,
    Eye,
} from 'lucide-vue-next';
import { edit as editEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { routes } from '@/lib/routes';
import { handleInertiaFormErrors, humanizeErrorMessage } from '@/lib/error-message';

const props = defineProps<{
    event: IEvent;
    forms: { id: string; title: string }[];
    cardShadow: string;
    /** URL halaman laporan & log kehadiran untuk acara ini. */
    laporanHref?: string | null;
}>();

const VISIBLE_LIMIT = 4;
const showAllForms = ref(false);
const visibleForms = computed(() => (showAllForms.value ? props.forms : props.forms.slice(0, VISIBLE_LIMIT)));
const hiddenCount = computed(() => Math.max(0, props.forms.length - VISIBLE_LIMIT));

const showDeleteModal = ref(false);
const deleteTarget = ref<{ id: string; title: string } | null>(null);
function startDelete(f: { id: string; title: string }): void {
    deleteTarget.value = f;
    showDeleteModal.value = true;
}
function confirmDelete(): void {
    if (!deleteTarget.value) return;
    router.delete(routes.admin.events.forms.destroy(props.event.id, deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(humanizeErrorMessage('Form deleted.'));
            showDeleteModal.value = false;
            deleteTarget.value = null;
        },
        onError: (errors: Record<string, string>) => handleInertiaFormErrors(errors, { title: 'Gagal menghapus form' }),
    });
}

defineEmits<{
    openArchive: [];
    openRestore: [];
}>();
</script>

<template>
    <aside class="flex min-w-0 flex-col gap-5 xl:sticky xl:top-20 xl:self-start">
        <Card :class="['border-border/60 rounded-2xl', cardShadow]">
            <CardHeader class="pb-3">
                <CardTitle class="text-muted-foreground text-[0.8125rem] font-semibold tracking-[0.1em] uppercase"
                    >Manage event</CardTitle
                >
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <Button class="h-auto min-h-10 w-full justify-start py-2 text-left whitespace-normal" as-child>
                    <Link :href="editEvent.url(event.id)"><Pencil class="mr-2 size-4" />Edit details</Link>
                </Button>
                <Button
                    variant="outline"
                    class="h-auto min-h-10 w-full justify-start py-2 text-left whitespace-normal"
                    as-child
                >
                    <Link :href="routes.admin.events.registrants(event.id)"
                        ><Users class="mr-2 size-4" />Manage registrants</Link
                    >
                </Button>
                <Button
                    v-if="laporanHref"
                    variant="outline"
                    class="h-auto min-h-10 w-full justify-start py-2 text-left whitespace-normal"
                    as-child
                >
                    <Link :href="laporanHref"><BarChart3 class="mr-2 size-4" />Laporan dan log kehadiran</Link>
                </Button>
            </CardContent>
        </Card>

        <Card :class="['border-border/60 rounded-2xl', cardShadow]">
            <CardHeader class="pb-1">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-muted-foreground text-[0.8125rem] font-semibold tracking-[0.1em] uppercase"
                        >Forms</CardTitle
                    >
                    <span class="text-foreground text-sm leading-none font-semibold tabular-nums">{{
                        props.forms.length
                    }}</span>
                </div>
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <!-- Empty state -->
                <div v-if="props.forms.length === 0" class="flex flex-col gap-1.5">
                    <p class="text-muted-foreground px-1 py-0.5 text-xs">Belum ada form untuk event ini.</p>
                    <Link
                        :href="routes.admin.events.forms.create(props.event.id)"
                        class="border-primary/30 bg-primary/5 text-primary hover:border-primary/50 hover:bg-primary/10 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed px-3 py-3 text-sm font-semibold transition-colors"
                    >
                        <Plus class="text-primary size-4" /> Buat form pertama
                    </Link>
                </div>

                <template v-else>
                    <div class="flex flex-col gap-1">
                        <div
                            v-for="form in visibleForms"
                            :key="form.id"
                            class="group border-border/50 bg-muted/30 hover:border-border/80 hover:bg-muted/50 flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 transition-colors"
                        >
                            <Link
                                :href="routes.admin.events.forms.show(props.event.id, form.id)"
                                class="flex min-w-0 flex-1 items-center gap-2 py-0.5"
                            >
                                <FileText class="text-muted-foreground size-3.5 shrink-0" />
                                <span class="text-foreground truncate text-xs font-medium">{{ form.title }}</span>
                            </Link>
                            <div
                                class="flex shrink-0 items-center gap-0.5 opacity-60 transition-opacity group-hover:opacity-100 focus-within:opacity-100"
                            >
                                <Link
                                    :href="routes.admin.events.forms.show(props.event.id, form.id)"
                                    class="text-muted-foreground hover:text-primary focus-visible:bg-background focus-visible:text-primary focus-visible:ring-ring/30 inline-flex size-7 items-center justify-center rounded-md transition-colors outline-none hover:bg-transparent focus-visible:ring-[3px]"
                                    :aria-label="`Detail form ${form.title}`"
                                >
                                    <Eye class="size-3.5" />
                                </Link>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground hover:text-destructive focus-visible:text-destructive size-7 shadow-none hover:bg-transparent"
                                    :aria-label="`Hapus form ${form.title}`"
                                    @click="startDelete(form)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="hiddenCount > 0"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground hover:text-foreground w-full justify-center text-xs"
                        @click="showAllForms = !showAllForms"
                    >
                        <span v-if="!showAllForms">Lihat {{ hiddenCount }} form lainnya</span>
                        <span v-else>Sembunyikan</span>
                        <ChevronDown v-if="!showAllForms" class="ml-1 size-3.5" />
                        <ChevronUp v-else class="ml-1 size-3.5" />
                    </Button>

                    <!-- Dashed CTA -->
                    <Link
                        :href="routes.admin.events.forms.create(props.event.id)"
                        class="border-primary/30 bg-primary/5 text-primary hover:border-primary/50 hover:bg-primary/10 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed px-3 py-2 text-sm font-semibold transition-colors"
                    >
                        <Plus class="text-primary size-4" /> Tambah form
                    </Link>
                </template>
            </CardContent>
        </Card>

        <Card :class="['border-border/60 rounded-2xl', cardShadow]">
            <CardHeader class="pb-3">
                <CardTitle class="text-muted-foreground text-[0.8125rem] font-semibold tracking-[0.1em] uppercase"
                    >Lifecycle</CardTitle
                >
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <Button
                    v-if="!event.deleted_at"
                    variant="outline"
                    size="sm"
                    class="border-destructive/20 text-destructive hover:bg-destructive/5 hover:text-destructive w-full justify-start"
                    @click="$emit('openArchive')"
                >
                    <Trash2 class="mr-2 size-4" />Archive event
                </Button>
                <Button v-else variant="outline" size="sm" class="w-full justify-start" @click="$emit('openRestore')">
                    <RotateCcw class="mr-2 size-4" />Restore event
                </Button>
                <Separator class="my-1" />
                <p class="text-muted-foreground px-1 text-[11px] leading-relaxed">
                    Archiving hides this event from the public but keeps all registrant data safe. You can restore it
                    anytime.
                </p>
            </CardContent>
        </Card>

        <ConfirmationModal
            :open="showDeleteModal"
            title="Hapus Form"
            :description="`Yakin hapus &quot;${deleteTarget?.title}&quot;? Tindakan tidak bisa dibatalkan.`"
            confirm-text="Hapus"
            variant="destructive"
            @confirm="confirmDelete"
            @cancel="showDeleteModal = false"
            @update:open="showDeleteModal = $event"
        />
    </aside>
</template>
