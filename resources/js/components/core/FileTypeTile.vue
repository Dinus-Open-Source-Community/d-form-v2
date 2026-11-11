<script setup lang="ts">
import { computed } from 'vue';
import csvIcon from '@/assets/file-types/csv.svg?url&no-inline';
import excelIcon from '@/assets/file-types/excel.svg?url&no-inline';
import genericIcon from '@/assets/file-types/generic.svg?url&no-inline';
import imageIcon from '@/assets/file-types/image.svg?url&no-inline';
import pdfIcon from '@/assets/file-types/pdf.svg?url&no-inline';
import powerpointIcon from '@/assets/file-types/powerpoint.svg?url&no-inline';
import textIcon from '@/assets/file-types/text.svg?url&no-inline';
import wordIcon from '@/assets/file-types/word.svg?url&no-inline';
import zipIcon from '@/assets/file-types/zip.svg?url&no-inline';
import { fileTypeIconKey, type TFileTypeIconKey } from '@/lib/fileTypeIcon';

const props = defineProps<{
    mime?: string;
    fileName?: string;
}>();

/** Aset vendored (vscode-icons, MIT) — lihat `assets/file-types/ATTRIBUTION.md`. */
const ICON_SOURCES: Record<TFileTypeIconKey, string> = {
    pdf: pdfIcon,
    excel: excelIcon,
    word: wordIcon,
    powerpoint: powerpointIcon,
    csv: csvIcon,
    zip: zipIcon,
    image: imageIcon,
    text: textIcon,
    generic: genericIcon,
};

const iconKey = computed(() => fileTypeIconKey(props.mime ?? '', props.fileName ?? ''));
</script>

<template>
    <!-- Ikon dekoratif: nama berkas di sebelahnya adalah label aksesibelnya. -->
    <img
        :src="ICON_SOURCES[iconKey]"
        alt=""
        width="40"
        height="40"
        class="h-[40px] w-[40px] shrink-0 object-contain"
        aria-hidden="true"
        draggable="false"
    />
</template>
