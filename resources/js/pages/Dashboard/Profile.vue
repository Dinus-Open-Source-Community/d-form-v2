<script setup lang="ts">
import { Head, usePage, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { toast } from 'vue-sonner'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import useAuth from '@/utils/composables/useAuth'
import { User as UserIcon, Shield, CalendarDays, Save, Lock } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

const page = usePage()
const user = useAuth(page.props)

const initials = computed<string>(() =>
    user.value?.name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) ?? '??',
)

const memberSince = computed<string>(() => {
    if (!user.value?.created_at) return 'Unknown'
    return new Date(user.value.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
})

const profileForm = useForm<{ name: string; email: string }>({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function updateProfile() {
    toast.success('Profile updated successfully.')
}

function updatePassword() {
    if (passwordForm.password !== passwordForm.password_confirmation) {
        toast.error('Passwords do not match.')
        return
    }
    toast.success('Password updated successfully.')
    passwordForm.reset()
}
</script>

<template>
    <Head title="Profile" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Profile" subtitle="Manage your account settings and preferences." />

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="rounded-xl border shadow-xs">
                <CardContent class="flex flex-col items-center gap-4 p-6">
                    <Avatar class="size-24">
                        <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                        <AvatarFallback class="bg-primary/10 text-xl font-semibold text-primary">{{ initials }}</AvatarFallback>
                    </Avatar>
                    <div class="text-center">
                        <h2 class="text-lg font-semibold">{{ user?.name ?? 'Guest' }}</h2>
                        <p class="text-sm text-muted-foreground">{{ user?.email ?? '' }}</p>
                    </div>
                    <div v-if="user?.roles?.length" class="flex flex-wrap justify-center gap-1.5">
                        <Badge v-for="role in user.roles" :key="role" variant="secondary" class="text-[10px] capitalize">
                            <Shield class="mr-1 size-3" />{{ role }}
                        </Badge>
                    </div>
                    <Separator />
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <CalendarDays class="size-3.5" />
                        <span>Member since {{ memberSince }}</span>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-6 lg:col-span-2">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base font-medium">
                            <UserIcon class="size-4" />Personal Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 pt-0">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <Label for="name" class="text-xs">Full Name</Label>
                                <Input id="name" v-model="profileForm.name" placeholder="Your full name" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label for="email" class="text-xs">Email Address</Label>
                                <Input id="email" type="email" v-model="profileForm.email" placeholder="your@email.com" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button :disabled="profileForm.processing" @click="updateProfile">
                                <Save class="mr-1.5 size-4" />Save Changes
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base font-medium">
                            <Lock class="size-4" />Change Password
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4 pt-0">
                        <div class="flex flex-col gap-1.5">
                            <Label for="current_password" class="text-xs">Current Password</Label>
                            <Input id="current_password" type="password" v-model="passwordForm.current_password" placeholder="Enter current password" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <Label for="new_password" class="text-xs">New Password</Label>
                                <Input id="new_password" type="password" v-model="passwordForm.password" placeholder="Enter new password" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label for="confirm_password" class="text-xs">Confirm Password</Label>
                                <Input id="confirm_password" type="password" v-model="passwordForm.password_confirmation" placeholder="Confirm new password" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button variant="outline" :disabled="passwordForm.processing" @click="updatePassword">
                                <Lock class="mr-1.5 size-4" />Update Password
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
