<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { User } from 'lucide-vue-next';
import { computed } from 'vue';

import { cn } from '@/lib/utils';
import { userAvatarFallbackClasses } from '@/lib/userAvatarFallback';

const props = withDefaults(
    defineProps<{
        /** avatar URL or empty */
        src: string | null | undefined;
        /** Stable seed (user id preferred, then email) for color selection */
        seed: string;
        /** Classes merged onto Avatar root (size, rounded, border, etc.) */
        avatarClass?: string;
        /** Border radius for fallback; should match the visible shape of the avatar */
        fallbackRoundClass?: string;
    }>(),
    { avatarClass: 'h-9 w-9', fallbackRoundClass: 'rounded-full' }
);

const palette = computed(() => userAvatarFallbackClasses(props.seed));

const rootClass = computed(() => cn(props.avatarClass));
</script>

<template>
    <Avatar :class="rootClass">
        <AvatarImage v-if="src" :src="src" :alt="''" class="size-full object-cover" />
        <AvatarFallback :class="cn(fallbackRoundClass, palette.bg, palette.icon)">
            <User class="size-[55%] shrink-0" />
        </AvatarFallback>
    </Avatar>
</template>
