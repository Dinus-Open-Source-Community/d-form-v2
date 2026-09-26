<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { routes } from '@/lib/routes'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface RoleOption {
    value: string
    label: string
}

const props = defineProps<{
    roleOptions: RoleOption[]
}>()

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.roleOptions[0]?.value ?? 'member',
})

const selectOptions = props.roleOptions.map(
    (option): SimpleSelectOption => ({ value: option.value, label: option.label }),
)

onMounted(() => {
    setTopbar({ title: 'Buat akun', subtitle: 'User management' })
})

function submit(): void {
    form.post(routes.admin.users.store)
}
</script>

<template>
    <Head title="Buat akun" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="font-display text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">
                    Buat akun
                </h1>
                <p class="text-muted-foreground mt-1.5 text-base">Tambah admin, member, atau staf rekrutmen</p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <Button as-child variant="outline" class="w-full sm:w-auto">
                    <Link :href="routes.admin.users.index">Batal</Link>
                </Button>
                <Button
                    type="submit"
                    form="user-create-form"
                    :disabled="form.processing"
                    class="w-full sm:w-auto"
                >
                    Simpan
                </Button>
            </div>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardContent class="p-6">
                <form id="user-create-form" class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="form.name" placeholder="Nama lengkap" required />
                        <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="user@example.com"
                            required
                        />
                        <p v-if="form.errors.email" class="text-destructive text-xs">{{ form.errors.email }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="password">Password</Label>
                            <Input id="password" v-model="form.password" type="password" required />
                            <p v-if="form.errors.password" class="text-destructive text-xs">
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="password_confirmation">Konfirmasi password</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="role">Role</Label>
                        <SimpleSelect
                            id="role"
                            v-model="form.role"
                            :options="selectOptions"
                            placeholder="Pilih role"
                        />
                        <p v-if="form.errors.role" class="text-destructive text-xs">{{ form.errors.role }}</p>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
