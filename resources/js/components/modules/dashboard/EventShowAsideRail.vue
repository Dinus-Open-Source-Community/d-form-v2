<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import {
    Pencil,
    Trash2,
    RotateCcw,
    FileText,
    Plus,
    ChevronDown,
    ChevronUp,
    Eye,
    Inbox,
    Globe,
    FilePenLine,
} from 'lucide-vue-next';
import { edit as editEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { routes } from '@/lib/routes';
import { useEventStatusToggle } from '@/hooks/useEventStatusToggle';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import type { IEventFormSummary } from '@/types/form';

const { handleInertiaFormErrors } = useToastNotifications();

const props = defineProps<{
    event: IEvent;
    forms: IEventFormSummary[];
    cardShadow: string;
}>();

const statusToggle = useEventStatusToggle(props.event.id);

/**
 * Kelas bersama semua aksi kartu: lebar penuh, tap target 44px, padding dan teks rata kiri yang sama.
 * Nilai piksel dipakai karena root font 15px di mobile membuat `min-h-11` hanya 41.25px.
 */
const ACTION_BUTTON_CLASS = 'h-auto min-h-[44px] w-full justify-start py-2 text-left whitespace-normal';

/** Copy modal konfirmasi mengikuti status tujuan; `targetStatus` null memakai copy draft (modal tertutup). */
const statusConfirmCopy = computed<{ title: string; description: string; confirmText: string }>(() => {
    if (statusToggle.targetStatus.value !== 'published') {
        return {
            title: 'Move this event to draft?',
            description:
                'The event will be hidden from the public. Registrant data is kept and can be restored by publishing again.',
            confirmText: 'Move to draft',
        };
    }

    return {
        title: 'Publish this event?',
        description:
            'Publishing makes this event visible to the public and opens it for registration according to its registration window.',
        confirmText: 'Publish event',
    };
});

const VISIBLE_LIMIT = 4;
const showAllForms = ref(false);
const visibleForms = computed(() => (showAllForms.value ? props.forms : props.forms.slice(0, VISIBLE_LIMIT)));
const hiddenCount = computed(() => Math.max(0, props.forms.length - VISIBLE_LIMIT));

/** Dua kelompok status: publikasi dan draft, dipisah divider dan diberi label berwarna. */
const formGroups = computed(() => {
    // 65% token + foreground menjaga hue hijau/emas sekaligus lolos kontras AA
    // (success 7.4:1, warning 5.0:1) pada latar kartu putih.
    const groups = [
        {
            key: 'published',
            label: 'Publish',
            labelClass: 'text-[color-mix(in_oklab,var(--success)_65%,var(--foreground))]',
            forms: visibleForms.value.filter((form) => !form.is_draft),
        },
        {
            key: 'draft',
            label: 'Draft',
            labelClass: 'text-[color-mix(in_oklab,var(--warning)_65%,var(--foreground))]',
            forms: visibleForms.value.filter((form) => form.is_draft),
        },
    ];

    return groups.filter((group) => group.forms.length > 0);
});

const showDeleteModal = ref(false);
const deleteTarget = ref<IEventFormSummary | null>(null);
function startDelete(f: IEventFormSummary): void {
    deleteTarget.value = f;
    showDeleteModal.value = true;
}
function confirmDelete(): void {
    if (!deleteTarget.value) return;
    router.delete(routes.admin.events.forms.destroy(props.event.id, deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Toast sukses berasal dari flash server (`FormController@destroy`).
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
    <aside class="flex w-full min-w-0 flex-col gap-5 xl:sticky xl:top-20 xl:self-start">
        <Card :class="['border-border/60 rounded-2xl', cardShadow]">
            <CardHeader>
                <!-- Label uppercase ber-tracking lebar adalah pola eyebrow design system aplikasi (sama di semua kartu), bukan heading marketing. -->
                <CardTitle class="text-muted-foreground text-[0.8125rem] font-semibold tracking-[0.1em] uppercase"
                    >Manage event</CardTitle
                >
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <Button
                    :variant="event.status === 'draft' && event.deleted_at === null ? 'outline' : 'default'"
                    :class="ACTION_BUTTON_CLASS"
                    as-child
                >
                    <Link :href="editEvent.url(event.id)"
                        ><Pencil class="mr-2 size-4" aria-hidden="true" />Edit details</Link
                    >
                </Button>
                <!-- Fitur broadcast dinonaktifkan (config/features.php): tombol Kirim Broadcast disembunyikan. -->

                <Button
                    v-if="!event.deleted_at && event.status === 'draft'"
                    :class="ACTION_BUTTON_CLASS"
                    @click="statusToggle.requestStatus('published')"
                >
                    <Globe class="mr-2 size-4" aria-hidden="true" />Publish event
                </Button>
                <Button
                    v-else-if="!event.deleted_at"
                    variant="outline"
                    :class="ACTION_BUTTON_CLASS"
                    @click="statusToggle.requestStatus('draft')"
                >
                    <FilePenLine class="mr-2 size-4" aria-hidden="true" />Move to draft
                </Button>

                <Button
                    v-if="!event.deleted_at"
                    variant="outline"
                    :class="[
                        ACTION_BUTTON_CLASS,
                        'border-destructive/20 text-destructive hover:bg-destructive/5 hover:text-destructive mt-2',
                    ]"
                    @click="$emit('openArchive')"
                >
                    <Trash2 class="mr-2 size-4" aria-hidden="true" />Archive event
                </Button>
                <Button v-else variant="outline" :class="[ACTION_BUTTON_CLASS, 'mt-2']" @click="$emit('openRestore')">
                    <RotateCcw class="mr-2 size-4" aria-hidden="true" />Restore event
                </Button>
            </CardContent>
        </Card>

        <Card :class="['border-border/60 rounded-2xl', cardShadow]">
            <CardHeader>
                <CardTitle class="text-muted-foreground text-[0.8125rem] font-semibold tracking-[0.1em] uppercase"
                    >Forms</CardTitle
                >
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <div v-if="props.forms.length === 0" class="flex flex-col gap-1.5">
                    <p class="text-muted-foreground px-1 py-0.5 text-xs">Belum ada form untuk event ini.</p>
                    <Link
                        :href="routes.admin.events.forms.create(props.event.id)"
                        class="border-primary/30 bg-primary/5 text-primary hover:border-primary/50 hover:bg-primary/10 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-dashed px-3 py-3 text-sm font-semibold transition-colors"
                    >
                        <Plus class="text-primary size-4" aria-hidden="true" /> Buat form pertama
                    </Link>
                </div>

                <template v-else>
                    <div class="flex flex-col gap-2">
                        <template v-for="(group, groupIndex) in formGroups" :key="group.key">
                            <Separator v-if="groupIndex > 0" class="bg-border/60" />

                            <section class="flex flex-col gap-1" :aria-label="`Form ${group.label}`">
                                <p
                                    class="px-1 text-xs font-semibold tracking-[0.12em] uppercase"
                                    :class="group.labelClass"
                                >
                                    {{ group.label }}
                                </p>

                                <div class="flex flex-col gap-1">
                                    <div
                                        v-for="form in group.forms"
                                        :key="form.id"
                                        class="group border-border/50 bg-muted/30 hover:border-border/80 hover:bg-muted/50 flex items-center gap-1 rounded-lg border px-2.5 py-1 transition-colors"
                                    >
                                        <Link
                                            :href="routes.admin.events.forms.show(props.event.id, form.id)"
                                            class="flex min-w-0 flex-1 items-center gap-2 self-stretch py-0.5"
                                        >
                                            <FileText
                                                class="text-muted-foreground size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span class="text-foreground truncate text-xs font-medium">{{
                                                form.title
                                            }}</span>
                                        </Link>
                                        <!-- Ikon 32px per pilihan user (tiga ikon terpisah): memenuhi minimum target WCAG 2.2 AA 24px, di bawah heuristik 44px. -->
                                        <div
                                            class="flex shrink-0 items-center justify-end gap-1 opacity-70 transition-opacity group-hover:opacity-100 focus-within:opacity-100"
                                        >
                                            <Link
                                                :href="routes.admin.events.forms.show(props.event.id, form.id)"
                                                class="text-muted-foreground hover:text-primary focus-visible:bg-background focus-visible:text-primary focus-visible:ring-ring/30 inline-flex size-8 items-center justify-center rounded-md transition-colors outline-none hover:bg-transparent focus-visible:ring-[3px]"
                                                :aria-label="`Detail form ${form.title}`"
                                            >
                                                <Eye class="size-3.5" aria-hidden="true" />
                                            </Link>
                                            <Link
                                                :href="`${routes.admin.events.forms.show(props.event.id, form.id)}?tab=jawaban`"
                                                class="text-muted-foreground hover:text-primary focus-visible:bg-background focus-visible:text-primary focus-visible:ring-ring/30 inline-flex size-8 items-center justify-center rounded-md transition-colors outline-none hover:bg-transparent focus-visible:ring-[3px]"
                                                :aria-label="`Lihat jawaban form ${form.title}`"
                                            >
                                                <Inbox class="size-3.5" aria-hidden="true" />
                                            </Link>
                                            <Button
                                                variant="ghost"
                                                class="text-muted-foreground hover:text-destructive focus-visible:text-destructive size-8 shadow-none hover:bg-transparent"
                                                :aria-label="`Hapus form ${form.title}`"
                                                @click="startDelete(form)"
                                            >
                                                <Trash2 class="size-3.5" aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </template>
                    </div>

                    <Button
                        v-if="hiddenCount > 0"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground min-h-[44px] w-full justify-center text-xs"
                        @click="showAllForms = !showAllForms"
                    >
                        <span v-if="!showAllForms">Lihat {{ hiddenCount }} form lainnya</span>
                        <span v-else>Sembunyikan</span>
                        <ChevronDown v-if="!showAllForms" class="ml-1 size-3.5" aria-hidden="true" />
                        <ChevronUp v-else class="ml-1 size-3.5" aria-hidden="true" />
                    </Button>

                    <Link
                        :href="routes.admin.events.forms.create(props.event.id)"
                        class="border-primary/30 bg-primary/5 text-primary hover:border-primary/50 hover:bg-primary/10 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-dashed px-3 py-2 text-sm font-semibold transition-colors"
                    >
                        <Plus class="text-primary size-4" aria-hidden="true" /> Tambah form
                    </Link>
                </template>
            </CardContent>
        </Card>

        <ConfirmationModal
            :open="statusToggle.confirmOpen.value"
            :title="statusConfirmCopy.title"
            :description="statusConfirmCopy.description"
            :confirm-text="statusConfirmCopy.confirmText"
            :loading="statusToggle.isSubmitting.value"
            @confirm="statusToggle.confirm"
            @cancel="statusToggle.cancel"
            @update:open="
                (open: boolean) => {
                    if (!open) statusToggle.cancel();
                }
            "
        />

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
