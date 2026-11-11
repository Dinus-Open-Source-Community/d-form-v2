<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { ImageOff } from 'lucide-vue-next';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { cn } from '@/lib/utils';

interface IEventBannerImageProps {
    /** Referensi banner dari server (absolut `/storage/...`, http(s), atau path disk). */
    src?: string | null;
    alt: string;
    imgClass?: string;
}

const props = withDefaults(defineProps<IEventBannerImageProps>(), { src: '', imgClass: '' });

const failed = ref(false);

function onError(): void {
    failed.value = true;
}

watch(
    () => props.src,
    () => {
        failed.value = false;
    }
);

/**
 * Satu-satunya normalisasi URL banner: delegasi ke `normalizePublicStorageUrl`
 * agar network-path (`//host`, `/\host`) dan varian multi-slash tetap inert.
 */
const resolvedSrc = computed<string>(() => normalizePublicStorageUrl(props.src ?? ''));

const showImg = computed<boolean>(() => resolvedSrc.value !== '' && !failed.value);
</script>

<template>
    <div class="bg-muted relative size-full overflow-hidden">
        <img
            v-if="showImg"
            :src="resolvedSrc"
            :alt="props.alt"
            loading="lazy"
            decoding="async"
            :class="cn('absolute inset-0 size-full object-cover', props.imgClass)"
            @error="onError"
        />
        <div
            v-else
            class="text-muted-foreground absolute inset-0 flex flex-col items-center justify-center gap-1.5 px-2 text-center"
        >
            <ImageOff class="size-6 shrink-0 opacity-50" :stroke-width="1.8" aria-hidden="true" />
            <span class="text-[10px] leading-tight font-medium">No banner</span>
        </div>
    </div>
</template>
