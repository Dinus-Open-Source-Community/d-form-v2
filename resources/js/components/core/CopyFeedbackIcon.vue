<script setup lang="ts">
import { Check, Copy } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        /** True selama konfirmasi salin aktif: ikon salin bertukar ke centang. */
        confirmed?: boolean;
        /** Kelas ukuran ikon, mis. `size-3.5` atau `size-4`. */
        iconClass?: string;
    }>(),
    { confirmed: false, iconClass: 'size-3.5' }
);

const iconTransition =
    'absolute inset-0 transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-none';
</script>

<template>
    <span :class="cn('relative inline-flex shrink-0', props.iconClass)" aria-hidden="true">
        <Copy
            :class="
                cn(iconTransition, props.iconClass, props.confirmed ? 'scale-75 opacity-0' : 'scale-100 opacity-100')
            "
        />
        <Check
            :class="
                cn(iconTransition, props.iconClass, props.confirmed ? 'scale-100 opacity-100' : 'scale-75 opacity-0')
            "
        />
    </span>
</template>
