<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { CircleAlert } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import ProfileHeader from '@/components/modules/profile/ProfileHeader.vue';
import ProfileSectionCard from '@/components/modules/profile/ProfileSectionCard.vue';
import ProfileIdentityPanel from '@/components/modules/profile/ProfileIdentityPanel.vue';
import ProfilePasswordPanel from '@/components/modules/profile/ProfilePasswordPanel.vue';
import ProfilePinCard from '@/components/modules/profile/ProfilePinCard.vue';
import ProfileConnectionsPanel from '@/components/modules/profile/ProfileConnectionsPanel.vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { useProfileIdentityForm } from '@/hooks/useProfileIdentityForm';
import { useProfilePasswordForm } from '@/hooks/useProfilePasswordForm';
import { useProfileAvatar } from '@/hooks/useProfileAvatar';
import { useProfileConnections } from '@/hooks/useProfileConnections';
import type { IProfilePageProps } from '@/types/profile';

defineOptions({ layout: DashboardLayout });

const props = withDefaults(defineProps<IProfilePageProps>(), {
    connections: () => [],
});

const page = usePage();
const user = computed<IUser | null>(() => page.props.auth.user ?? null);

/**
 * Halaman ini hanya dirender untuk pengguna yang sudah masuk. Fallback menjaga
 * tipe saat props belum tersedia, bukan menggantikan perilaku sesi.
 */
const AUTH_FALLBACK_USER: IUser = { id: '', name: '', email: '', avatar: null };
const setupUser: IUser = user.value ?? AUTH_FALLBACK_USER;

const identity = useProfileIdentityForm(setupUser);
const password = useProfilePasswordForm(setupUser);
const avatar = useProfileAvatar(() => user.value?.avatar ?? null);
const connectionActions = useProfileConnections();

const { form: identityForm, isDirty: isIdentityDirty } = identity;
const { form: passwordForm, isDirty: isPasswordDirty, hasLocalPassword } = password;
</script>

<template>
    <Head title="Profil" />

    <div data-testid="profile-page" class="mx-auto w-full max-w-5xl">
        <div class="@container mx-auto max-w-[48rem] min-w-0">
            <ProfileHeader :user="user" :avatar="avatar" />

            <div class="border-border bg-card min-w-0 rounded-2xl border shadow-xs">
                <ProfileSectionCard section-key="identity">
                    <ProfileIdentityPanel :user="user" :identity="identity" />

                    <template #footer>
                        <p class="text-muted-foreground min-w-0 text-sm @min-[640px]:mr-auto">
                            <span
                                v-if="isIdentityDirty"
                                data-testid="profile-identity-dirty"
                                class="text-foreground inline-flex items-center gap-1.5"
                            >
                                <CircleAlert class="size-4 shrink-0" aria-hidden="true" />
                                Belum disimpan
                            </span>
                            <span v-else>Tidak ada perubahan.</span>
                        </p>

                        <Button
                            type="submit"
                            form="profile-identity-form"
                            data-testid="profile-identity-save"
                            class="min-h-[44px] w-full @min-[640px]:w-auto"
                            :disabled="!isIdentityDirty || identityForm.processing"
                        >
                            Simpan profil
                        </Button>
                    </template>
                </ProfileSectionCard>

                <ProfileSectionCard section-key="security">
                    <div class="grid min-w-0 gap-6">
                        <ProfilePinCard :has-local-password="hasLocalPassword" />

                        <div class="border-border min-w-0 border-t pt-6">
                            <ProfilePasswordPanel :password="password" />
                        </div>
                    </div>

                    <template #footer>
                        <Button
                            type="submit"
                            form="profile-password-form"
                            data-testid="profile-password-save"
                            class="min-h-[44px] w-full @min-[640px]:w-auto"
                            :disabled="!isPasswordDirty || passwordForm.processing"
                        >
                            Simpan kata sandi
                        </Button>
                    </template>
                </ProfileSectionCard>

                <ProfileSectionCard section-key="connections">
                    <ProfileConnectionsPanel :connections="props.connections" :actions="connectionActions" />
                </ProfileSectionCard>
            </div>
        </div>
    </div>
</template>
