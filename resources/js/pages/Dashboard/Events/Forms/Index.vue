<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Plus, FileText, Pencil, Trash2, Inbox, CalendarClock, Users } from 'lucide-vue-next'
import { formatDateTime } from '@/lib/dummyData'
import FormSubmissionsController from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormSubmissionsController'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: { id: string; title: string }
    forms: IForm[]
}>()

const showDeleteModal = ref(false)
const deleteTarget = ref<IForm | null>(null)

function startDelete(form: IForm) {
    deleteTarget.value = form
    showDeleteModal.value = true
}

function confirmDelete() {
    if (!deleteTarget.value) return
    const id = deleteTarget.value.id
    router.delete(routes.admin.events.forms.destroy(props.event.id, id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Form deleted.')
            showDeleteModal.value = false
            deleteTarget.value = null
        },
    })
}

function submissionsHref(formId: string): string {
    return FormSubmissionsController.url({ event: props.event.id, form: formId })
}
</script>

<template>
    <Head title="Forms" />

    <div class="flex min-w-0 flex-col gap-5 sm:gap-6">
        <PageHeader
            title="Forms"
            :subtitle="`Manage forms for ${event.title}.`"
            :back-href="routes.admin.events.show(event.id)"
        >
            <template #actions>
                <Button as-child class="h-11 w-full rounded-xl md:h-10 md:w-auto">
                    <Link
                        :href="routes.admin.events.forms.create(event.id)"
                        class="inline-flex items-center justify-center gap-2"
                    >
                        <Plus class="size-4" />
                        Create Form
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="forms.length > 0" class="grid min-w-0 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
            <Card
                v-for="form in forms"
                :key="form.id"
                class="group overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md"
            >
                <CardContent class="flex h-full flex-col p-0">
                    <div class="flex min-w-0 flex-1 items-start gap-3 p-4 sm:p-5">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/10"
                        >
                            <FileText class="size-4.5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h3 class="line-clamp-2 text-sm font-semibold leading-snug tracking-tight text-foreground">
                                {{ form.title }}
                            </h3>
                            <p
                                v-if="form.description"
                                class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ form.description }}
                            </p>
                            <p v-else class="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                                Tidak ada deskripsi form.
                            </p>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="vis in form.visible_for ?? []"
                                    :key="vis"
                                    variant="secondary"
                                    class="rounded-full px-2 text-[10px] font-medium capitalize"
                                >
                                    {{ vis }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-border/60 bg-muted/20 px-4 py-3 sm:px-5">
                        <div class="grid gap-2 text-xs text-muted-foreground">
                            <div class="flex min-w-0 items-center gap-2">
                                <CalendarClock class="size-3.5 shrink-0 text-muted-foreground/70" />
                                <span class="shrink-0">Tutup</span>
                                <span class="min-w-0 truncate font-medium text-foreground">
                                    {{ form.closed_at ? formatDateTime(form.closed_at) : 'Belum diatur' }}
                                </span>
                            </div>
                            <div class="flex min-w-0 items-center gap-2">
                                <Users class="size-3.5 shrink-0 text-muted-foreground/70" />
                                <span class="shrink-0">Akses</span>
                                <span class="min-w-0 truncate font-medium text-foreground">
                                    {{
                                        (form.visible_for?.length ?? 0)
                                            ? (form.visible_for ?? []).join(', ')
                                            : 'Semua peserta'
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-col gap-2 sm:grid sm:grid-cols-3 sm:gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-11 w-full rounded-xl border-border/80 bg-background/80 px-3 text-xs font-medium text-primary hover:text-primary sm:h-9 sm:min-w-0"
                                as-child
                            >
                                <Link
                                    :href="submissionsHref(form.id)"
                                    :prefetch="false"
                                    title="Data pengiriman"
                                    class="inline-flex min-w-0 items-center justify-center gap-1.5"
                                >
                                    <Inbox class="size-3.5 shrink-0" aria-hidden="true" />
                                    <span>Data</span>
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-11 w-full rounded-xl border-border/80 bg-background/80 px-3 text-xs font-medium sm:h-9 sm:min-w-0"
                                as-child
                            >
                                <Link
                                    :href="routes.admin.events.forms.show(event.id, form.id)"
                                    :prefetch="false"
                                    title="Edit form"
                                    class="inline-flex min-w-0 items-center justify-center gap-1.5"
                                >
                                    <Pencil class="size-3.5 shrink-0" aria-hidden="true" />
                                    <span>Edit</span>
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border-destructive/25 bg-background/80 px-3 text-xs font-medium text-destructive hover:border-destructive/35 hover:bg-destructive/10 hover:text-destructive sm:h-9 sm:min-w-0"
                                @click="startDelete(form)"
                            >
                                <Trash2 class="size-3.5 shrink-0" aria-hidden="true" />
                                <span>Hapus</span>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <EmptyState
            v-else
            title="No forms yet"
            description="Create your first form for this event."
            animation-name="errorState"
        >
            <Button as-child class="h-11 w-full rounded-xl sm:w-auto">
                <Link
                    :href="routes.admin.events.forms.create(event.id)"
                    class="inline-flex items-center justify-center gap-2"
                >
                    <Plus class="size-4" />
                    Create Form
                </Link>
            </Button>
        </EmptyState>
    </div>

    <ConfirmationModal
        :open="showDeleteModal"
        title="Delete Form"
        :description="`Are you sure you want to delete &quot;${deleteTarget?.title}&quot;? This action cannot be undone.`"
        confirm-text="Delete"
        variant="destructive"
        @confirm="confirmDelete"
        @cancel="showDeleteModal = false"
        @update:open="showDeleteModal = $event"
    />
</template>
