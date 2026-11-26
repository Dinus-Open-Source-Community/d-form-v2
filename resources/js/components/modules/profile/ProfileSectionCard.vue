<script setup lang="ts">
import { computed } from 'vue';
import { PROFILE_SECTIONS } from '@/constants/profileConnections';
import type { IProfileSectionMeta, TProfileSection } from '@/types/profile';

/**
 * Kerangka satu bagian halaman profil: judul `h2`, satu kalimat penjelasan, isi,
 * lalu footer aksi. Semua bagian memakai komponen ini supaya pembatas antar
 * bagian, jarak vertikal, dan tata letak footer hanya ditulis sekali.
 */
const props = defineProps<{
    sectionKey: TProfileSection;
}>();

const section = computed<IProfileSectionMeta>(() => PROFILE_SECTIONS[props.sectionKey]);
</script>

<template>
    <section
        :id="section.anchorId"
        :data-testid="`profile-section-${section.key}`"
        :aria-labelledby="`profile-heading-${section.key}`"
        class="border-border min-w-0 scroll-mt-24 border-t px-5 py-8 first:border-t-0 @min-[640px]:px-8 @min-[640px]:py-10"
    >
        <h2 :id="`profile-heading-${section.key}`" class="text-foreground text-base font-semibold tracking-[-0.01em]">
            {{ section.label }}
        </h2>
        <p class="text-muted-foreground mt-1 text-sm wrap-anywhere">{{ section.description }}</p>

        <div class="mt-6 min-w-0">
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="mt-6 flex flex-col gap-3 @min-[640px]:flex-row @min-[640px]:items-center @min-[640px]:justify-end"
        >
            <slot name="footer" />
        </div>
    </section>
</template>
