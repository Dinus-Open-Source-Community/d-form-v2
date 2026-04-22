<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Plus, FileText, Pencil, Trash2 } from 'lucide-vue-next'
import { dummyForms, dummyEvents, formatDateTime } from '@/lib/dummyData'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event?: { id: string; title: string }
    forms?: IForm[]
}>()

const eventContext = props.event ?? { id: dummyEvents[0].id, title: dummyEvents[0].title }
const forms = ref(props.forms ?? dummyForms.filter((f) => f.event_id === eventContext.id))

const showDeleteModal = ref(false)
const deleteTarget = ref<IForm | null>(null)

function startDelete(form: IForm) { deleteTarget.value = form; showDeleteModal.value = true }
function confirmDelete() {
    if (deleteTarget.value) {
        forms.value = forms.value.filter((f) => f.id !== deleteTarget.value!.id)
        toast.success(`Form "${deleteTarget.value.title}" deleted.`)
    }
    showDeleteModal.value = false
}
function handleEdit(form: IForm) { toast.info(`Editing "${form.title}"...`) }
</script>

<template>
    <Head title="Forms" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Forms" :subtitle="`Manage forms for ${eventContext.title}.`">
            <template #actions>
                <Button as-child>
                    <Link :href="`/dashboard/events/${eventContext.id}/forms/create`">
                        <Plus class="mr-1.5 size-4" />
                        Create Form
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="forms.length > 0" class="grid gap-4 sm:grid-cols-2">
            <Card v-for="form in forms" :key="form.id" class="rounded-xl border shadow-xs transition-all duration-200 hover:shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <FileText class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold">{{ form.title }}</h3>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ form.description }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="vis in form.visible_for" :key="vis" variant="secondary" class="text-[10px] capitalize">{{ vis }}</Badge>
                                </div>
                                <p class="mt-2 text-[10px] text-muted-foreground">Closes: {{ formatDateTime(form.closed_at) }}</p>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Button variant="ghost" size="icon" class="size-7" @click="handleEdit(form)"><Pencil class="size-3.5" /></Button>
                            <Button variant="ghost" size="icon" class="size-7 text-destructive hover:text-destructive" @click="startDelete(form)"><Trash2 class="size-3.5" /></Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <EmptyState v-else title="No forms yet" description="Create your first form for this event." animation-url="https://lottie.host/4e039bf3-670e-4a0f-8a6c-1bee793bfc23/JkaGBMIxOz.json">
            <Button as-child>
                <Link :href="`/dashboard/events/${eventContext.id}/forms/create`"><Plus class="mr-1.5 size-4" />Create Form</Link>
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
