<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    template: {
        id: string
        event_type: string
        subject: string
        body_html: string
        body_text: string | null
        is_active: boolean
        available_variables: string[]
    }
}>()

const form = useForm({
    subject: props.template.subject,
    body_html: props.template.body_html,
    body_text: props.template.body_text ?? '',
    is_active: props.template.is_active,
})

onMounted(() => {
    setTopbar({ title: 'Edit template', subtitle: props.template.event_type })
})

function submit() {
    form.put(routes.admin.recruitment.emailTemplates.update(props.template.id))
}
</script>

<template>
    <Head :title="`Edit ${template.event_type}`" />

    <div class="mx-auto flex max-w-3xl flex-col gap-6">
        <PageHeader
            :title="template.event_type"
            subtitle="Gunakan variabel {{nama_variabel}} di subject/body."
            :back-href="routes.admin.recruitment.emailTemplates.index"
        />

        <Card class="rounded-2xl border-border/70">
            <CardContent class="space-y-4 p-6">
                <p class="text-muted-foreground text-xs">
                    Variabel:
                    <span v-for="(v, i) in template.available_variables" :key="v">
                        {{ i > 0 ? ', ' : '' }}{{ '{' }}{{ '{' }}{{ v }}{{ '}' }}{{ '}' }}
                    </span>
                </p>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="subject">Subject</Label>
                        <input
                            id="subject"
                            v-model="form.subject"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="body_html">Body HTML</Label>
                        <textarea
                            id="body_html"
                            v-model="form.body_html"
                            rows="12"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 font-mono text-sm"
                            required
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="is_active" v-model="form.is_active" type="checkbox" class="size-4" />
                        <Label for="is_active">Template aktif</Label>
                    </div>

                    <Button type="submit" :disabled="form.processing">Simpan</Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
