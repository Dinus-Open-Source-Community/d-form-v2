<script setup lang="ts">
import { computed } from 'vue';
import { Plus, RotateCcw, SearchX, Users } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    hasActiveFilters: boolean;
}>();

const emit = defineEmits<{
    create: [];
    reset: [];
}>();

const title = computed<string>(() => (props.hasActiveFilters ? 'Tidak ada akun yang cocok' : 'Belum ada akun'));

const description = computed<string>(() =>
    props.hasActiveFilters
        ? 'Coba kata kunci lain atau pilih role yang berbeda.'
        : 'Akun admin, member, dan staf rekrutmen akan muncul di sini setelah dibuat.'
);
</script>

<template>
    <div data-testid="user-directory-empty" class="flex flex-col items-center gap-2 px-4 py-12 text-center">
        <div class="bg-muted/40 text-muted-foreground flex size-11 items-center justify-center rounded-full">
            <SearchX v-if="hasActiveFilters" class="size-5" aria-hidden="true" />
            <Users v-else class="size-5" aria-hidden="true" />
        </div>

        <p class="text-foreground text-sm font-medium">{{ title }}</p>
        <p class="text-muted-foreground max-w-sm text-xs">{{ description }}</p>

        <Button v-if="hasActiveFilters" variant="outline" size="sm" class="mt-2" @click="emit('reset')">
            <RotateCcw class="size-4" aria-hidden="true" />
            Hapus filter
        </Button>
        <Button v-else size="sm" class="mt-2" @click="emit('create')">
            <Plus class="size-4" aria-hidden="true" />
            Buat akun
        </Button>
    </div>
</template>
