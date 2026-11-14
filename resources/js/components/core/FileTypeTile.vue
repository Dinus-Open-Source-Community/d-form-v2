<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { FileText } from 'lucide-vue-next';
import { fileTypeIconKey, type TFileTypeIconKey } from '@/lib/fileTypeIcon';

const props = defineProps<{
    mime?: string;
    fileName?: string;
}>();

/** Aset statis di public: URL relatif tetap sama pada Vite dev dan PHP build. */
const ICON_PATHS: Record<TFileTypeIconKey, string> = {
    pdf: '/file-types/pdf.svg',
    excel: '/file-types/excel.svg',
    word: '/file-types/word.svg',
    powerpoint: '/file-types/powerpoint.svg',
    csv: '/file-types/csv.svg',
    zip: '/file-types/zip.svg',
    image: '/file-types/image.svg',
    text: '/file-types/text.svg',
    generic: '/file-types/generic.svg',
};

const iconKey = computed(() => fileTypeIconKey(props.mime ?? '', props.fileName ?? ''));
const iconSource = computed(() => ICON_PATHS[iconKey.value]);
const iconFailed = ref(false);

watch(iconSource, () => {
    iconFailed.value = false;
});

function onIconError(): void {
    iconFailed.value = true;
}
</script>

<template>
    <!-- Decorative icon; the adjacent filename provides the accessible name. -->
    <img
        v-if="!iconFailed"
        :src="iconSource"
        alt=""
        width="40"
        height="40"
        class="h-[40px] w-[40px] shrink-0 object-contain"
        aria-hidden="true"
        draggable="false"
        @error="onIconError"
    />
    <span
        v-else
        class="border-border bg-muted text-muted-foreground grid size-10 shrink-0 place-items-center rounded-md border"
        aria-hidden="true"
        data-testid="file-type-icon-fallback"
    >
        <FileText class="size-5" />
    </span>
</template>
