<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { showErrorToast } from '@/lib/error-message'
import { routes } from '@/lib/routes'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import { ChevronLeft, ChevronRight, Eye, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface ManagedUser {
    id: string
    name: string
    email: string
    roles: string[]
    created_at: string | null
    deleted_at: string | null
}

interface RoleOption {
    value: string
    label: string
}

interface UsersQuery {
    search?: string
    role?: string | null
    per_page?: number
}

interface UsersPaginator {
    data: ManagedUser[]
    current_page: number
    last_page: number
    per_page: number
    total: number
}

const props = defineProps<{
    users: UsersPaginator
    roleOptions: RoleOption[]
    query: UsersQuery
}>()

const page = usePage()
const authUser = useAuth(page.props)

const search = ref(props.query.search ?? '')
const role = ref(props.query.role ?? '')

const roleFilterOptions = computed<SimpleSelectOption[]>(() => [
    { value: '', label: 'Semua role' },
    ...props.roleOptions.map((option) => ({ value: option.value, label: option.label })),
])

const roleLabelMap = computed(() =>
    Object.fromEntries(props.roleOptions.map((option) => [option.value, option.label])),
)

const hasActiveFilters = computed(() => Boolean(search.value || role.value))

const perPage = computed(() => props.users.per_page || props.query.per_page || 10)

const rangeLabel = computed(() => {
    if (props.users.total === 0) return 'Tidak ada data'
    const from = (props.users.current_page - 1) * perPage.value + 1
    const to = Math.min(props.users.total, props.users.current_page * perPage.value)
    return `Menampilkan ${from}–${to} dari ${props.users.total}`
})

onMounted(() => {
    setTopbar({ title: 'Pengguna', subtitle: 'Kelola akun admin dan member' })
})

function applyFilters(pageNumber: number = 1): void {
    router.get(
        routes.admin.users.index,
        {
            search: search.value || undefined,
            role: role.value || undefined,
            page: pageNumber > 1 ? pageNumber : undefined,
        },
        { preserveState: true, replace: true },
    )
}

watch([search, role], () => applyFilters())

function resetFilters(): void {
    search.value = ''
    role.value = ''
}

function formatRoles(roles: string[]): string {
    if (roles.length === 0) return '—'
    return roles.map((name) => roleLabelMap.value[name] ?? name).join(', ')
}

function isSuperAdminTarget(user: ManagedUser): boolean {
    return user.roles.includes('super-admin')
}

function isSelf(user: ManagedUser): boolean {
    return authUser.value?.id === user.id
}

function canEdit(user: ManagedUser): boolean {
    return !isSuperAdminTarget(user)
}

function canDelete(user: ManagedUser): boolean {
    return !isSuperAdminTarget(user) && !isSelf(user)
}

const deleteTarget = ref<ManagedUser | null>(null)
const isDeleting = ref(false)
const showDeleteModal = computed(() => deleteTarget.value !== null)

const deleteDescription = computed(() => {
    const name = deleteTarget.value?.name
    return name
        ? `Akun “${name}” akan dihapus (soft delete). Tindakan ini bisa memengaruhi akses dashboard.`
        : 'Akun akan dihapus (soft delete).'
})

function startDelete(user: ManagedUser): void {
    if (!canDelete(user)) return
    deleteTarget.value = user
}

function cancelDelete(): void {
    if (isDeleting.value) return
    deleteTarget.value = null
}

function confirmDelete(): void {
    const target = deleteTarget.value
    if (!target || isDeleting.value) return
    isDeleting.value = true
    router.delete(routes.admin.users.destroy(target.id), {
        preserveScroll: true,
        onError: () => showErrorToast('Gagal menghapus akun.'),
        onFinish: () => {
            isDeleting.value = false
            deleteTarget.value = null
        },
    })
}
</script>

<template>
    <Head title="Pengguna" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="font-display text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">
                    Pengguna
                </h1>
                <p class="text-muted-foreground mt-1.5 text-base">Kelola akun admin dan member</p>
            </div>
            <Button as-child class="w-full shrink-0 sm:w-auto">
                <Link :href="routes.admin.users.create">
                    <Plus class="mr-2 size-4" aria-hidden="true" />
                    Buat akun
                </Link>
            </Button>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama atau email…"
                        class="pl-9"
                    />
                </div>
                <SimpleSelect
                    v-model="role"
                    :options="roleFilterOptions"
                    placeholder="Filter role"
                    class="w-full sm:w-56"
                />
            </CardContent>
        </Card>

        <template v-if="users.data.length > 0">
            <Card class="overflow-hidden rounded-2xl border-border/70">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead class="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in users.data" :key="row.id">
                                <TableCell class="font-medium">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link
                                            :href="routes.admin.users.show(row.id)"
                                            class="hover:underline"
                                        >
                                            {{ row.name }}
                                        </Link>
                                        <Badge v-if="isSelf(row)" variant="secondary">Anda</Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ row.email }}</TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Badge
                                            v-for="roleName in row.roles"
                                            :key="`${row.id}-${roleName}`"
                                            variant="outline"
                                        >
                                            {{ roleLabelMap[roleName] ?? roleName }}
                                        </Badge>
                                        <span v-if="row.roles.length === 0" class="text-muted-foreground text-sm">
                                            {{ formatRoles(row.roles) }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            as-child
                                            variant="ghost"
                                            size="icon-sm"
                                            :aria-label="`Detail ${row.name}`"
                                        >
                                            <Link :href="routes.admin.users.show(row.id)">
                                                <Eye class="size-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            v-if="canEdit(row)"
                                            as-child
                                            variant="ghost"
                                            size="icon-sm"
                                            :aria-label="`Edit ${row.name}`"
                                        >
                                            <Link :href="routes.admin.users.edit(row.id)">
                                                <Pencil class="size-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            v-if="canDelete(row)"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            :aria-label="`Hapus ${row.name}`"
                                            @click="startDelete(row)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </Card>

            <div v-if="users.last_page > 1" class="flex flex-col items-center gap-3">
                <Pagination
                    :page="users.current_page"
                    :total="users.total"
                    :items-per-page="perPage"
                    :sibling-count="1"
                    @update:page="applyFilters"
                >
                    <PaginationContent v-slot="{ items }">
                        <PaginationPrevious>
                            <ChevronLeft class="size-4" aria-hidden="true" />
                            <span class="hidden sm:block">Sebelumnya</span>
                        </PaginationPrevious>
                        <template v-for="(item, index) in items" :key="index">
                            <PaginationItem
                                v-if="item.type === 'page'"
                                :value="item.value"
                                :is-active="item.value === users.current_page"
                                :aria-label="`Ke halaman ${item.value}`"
                            >
                                {{ item.value }}
                            </PaginationItem>
                            <PaginationEllipsis v-else :index="index" />
                        </template>
                        <PaginationNext>
                            <span class="hidden sm:block">Berikutnya</span>
                            <ChevronRight class="size-4" aria-hidden="true" />
                        </PaginationNext>
                    </PaginationContent>
                </Pagination>
                <p class="text-muted-foreground text-sm">{{ rangeLabel }}</p>
            </div>
            <p v-else class="text-muted-foreground text-center text-sm">{{ rangeLabel }}</p>
        </template>

        <EmptyState
            v-else
            :title="hasActiveFilters ? 'Tidak ada hasil' : 'Belum ada pengguna'"
            :description="
                hasActiveFilters
                    ? 'Coba ubah pencarian atau filter role.'
                    : 'Buat akun baru untuk admin atau member.'
            "
            animation-name="emptyData"
        >
            <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="resetFilters">
                <RotateCcw class="mr-2 size-4" aria-hidden="true" />
                Atur ulang filter
            </Button>
            <Button v-else as-child size="sm">
                <Link :href="routes.admin.users.create">
                    <Plus class="mr-2 size-4" aria-hidden="true" />
                    Buat akun
                </Link>
            </Button>
        </EmptyState>

        <ConfirmationModal
            :open="showDeleteModal"
            title="Hapus akun?"
            :description="deleteDescription"
            confirm-text="Hapus"
            cancel-text="Batal"
            variant="destructive"
            :loading="isDeleting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
            @update:open="(v: boolean) => { if (!v) cancelDelete() }"
        />
    </div>
</template>
