<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import type { IUserRoleOption } from '@/types/user-management';

defineProps<{
    search: string;
    role: string;
    roleOptions: IUserRoleOption[];
    /** Ringkasan halaman aktif; sudah diformat pemanggil agar angka hanya diformat sekali. */
    resultSummary: string;
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:role': [value: string];
}>();

/** Chip aktif memakai token primary; chip pasif tetap tenang sampai disentuh. */
function chipClass(isActive: boolean): string {
    return isActive
        ? 'border-primary bg-primary/10 text-primary'
        : 'border-border bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground';
}
</script>

<template>
    <div data-testid="user-toolbar" class="border-border flex flex-col gap-3 border-b px-4 py-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
            <div class="relative min-w-0 sm:max-w-xs sm:flex-1">
                <label for="user-directory-search" class="sr-only">Cari pengguna</label>
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                    aria-hidden="true"
                />
                <Input
                    id="user-directory-search"
                    data-testid="user-search"
                    :model-value="search"
                    type="search"
                    placeholder="Cari nama atau email"
                    autocomplete="off"
                    class="pr-12 pl-9 sm:pr-9 [&::-webkit-search-cancel-button]:hidden"
                    @update:model-value="emit('update:search', String($event))"
                />
                <button
                    v-if="search !== ''"
                    type="button"
                    class="text-muted-foreground hover:text-foreground absolute top-1/2 right-0 flex size-[44px] -translate-y-1/2 items-center justify-center rounded-md transition-colors sm:size-7"
                    aria-label="Bersihkan pencarian"
                    @click="emit('update:search', '')"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>

            <p data-testid="user-result-count" aria-live="polite" class="text-muted-foreground text-xs sm:ml-auto">
                {{ resultSummary }}
            </p>
        </div>

        <div class="-mx-1 flex scrollbar-none gap-2 overflow-x-auto px-1 sm:flex-wrap sm:overflow-visible">
            <button
                type="button"
                data-testid="user-role-filter"
                data-role=""
                :aria-pressed="role === ''"
                class="inline-flex min-h-[44px] shrink-0 items-center rounded-lg border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:min-h-0"
                :class="chipClass(role === '')"
                @click="emit('update:role', '')"
            >
                Semua
            </button>
            <button
                v-for="option in roleOptions"
                :key="option.value"
                type="button"
                data-testid="user-role-filter"
                :data-role="option.value"
                :aria-pressed="role === option.value"
                class="inline-flex min-h-[44px] shrink-0 items-center rounded-lg border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:min-h-0"
                :class="chipClass(role === option.value)"
                @click="emit('update:role', option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
